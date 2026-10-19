<?php
/**
 * Window Audit (read-only): compares saved report items against the old (2.3.4) and new (2.3.6+)
 * Past/Future date windows. Under Bimonthly Updates > Window Audit, administrators only.
 */
if (!defined('ABSPATH')) exit;

function brmwa_periods() {
    return array(
        'jan-feb' => array('Jan–Feb', 1, 2),
        'mar-apr' => array('Mar–Apr', 3, 4),
        'may-jun' => array('May–Jun', 5, 6),
        'jul-aug' => array('Jul–Aug', 7, 8),
        'sep-oct' => array('Sep–Oct', 9, 10),
        'nov-dec' => array('Nov–Dec', 11, 12),
    );
}

/** Old (2.3.4) and new (2.3.6) windows for a period key + year: array('old'=>[dir=>[start,end]], 'new'=>…) */
function brmwa_windows($key, $year) {
    $periods = brmwa_periods();
    if (!isset($periods[$key]) || !$year) return false;
    $keys = array_keys($periods);
    $idx  = array_search($key, $keys);
    $s = $periods[$key][1];
    $e = $periods[$key][2];
    $nxt = $periods[$keys[($idx + 1) % 6]];
    $ny  = ($idx === 5) ? $year + 1 : $year;
    $f = function ($ts) { return date('Y-m-d', $ts); };
    return array(
        'old' => array(
            'prior' => array($f(mktime(0, 0, 0, $s, 1, $year)),       $f(mktime(0, 0, 0, $e + 1, 0, $year))),
            'ahead' => array($f(mktime(0, 0, 0, $nxt[1], 1, $ny)),    $f(mktime(0, 0, 0, $nxt[2] + 1, 0, $ny))),
        ),
        'new' => array(
            'prior' => array($f(mktime(0, 0, 0, $s - 1, 1, $year)),   $f(mktime(0, 0, 0, $s + 1, 0, $year))),
            'ahead' => array($f(mktime(0, 0, 0, $e, 1, $year)),       $f(mktime(0, 0, 0, $e + 2, 0, $year))),
        ),
    );
}

/** The date the picker filters on, for each post type (Y-m-d, or '' if none) */
function brmwa_item_date($post_id) {
    $type  = get_post_type($post_id);
    $field = array('event' => 'event_date_start', 'products_and_resourc' => 'publication_date', 'bimonthly-highlight' => 'highlight_date');
    if (isset($field[$type])) {
        $raw = function_exists('get_field') ? get_field($field[$type], $post_id) : get_post_meta($post_id, $field[$type], true);
    } else {
        $raw = get_post_field('post_date', $post_id);
    }
    $ts = $raw ? strtotime(is_string($raw) ? $raw : '') : false;
    return $ts ? date('Y-m-d', $ts) : '';
}

function brmwa_render_page() {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions');

    $periods = brmwa_periods();
    $f_year   = isset($_GET['yr']) ? intval($_GET['yr']) : 0;
    $f_period = isset($_GET['per']) && isset($periods[$_GET['per']]) ? $_GET['per'] : '';
    $f_group  = isset($_GET['grp']) ? sanitize_text_field(wp_unslash($_GET['grp'])) : '';
    $f_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
    $f_q      = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    $sorts    = array('period', 'report', 'group', 'old_only', 'new_only', 'neither', 'both');
    $orderby  = isset($_GET['orderby']) && in_array($_GET['orderby'], $sorts, true) ? $_GET['orderby'] : 'period';
    $order    = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'asc' : (isset($_GET['order']) ? 'desc' : ($orderby === 'period' || strpos($orderby, '_') !== false || in_array($orderby, array('neither', 'both'), true) ? 'desc' : 'asc'));

    $reports = get_posts(array('post_type' => 'bimonthly', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC'));

    $all = array();
    $no_period = 0;
    foreach ($reports as $r) {
        $key  = get_post_meta($r->ID, '_brm_period', true);
        $year = intval(get_post_meta($r->ID, '_brm_year', true));
        $win  = brmwa_windows($key, $year);
        if (!$win) { $no_period++; continue; }

        $counts = array('both' => 0, 'old_only' => 0, 'new_only' => 0, 'neither' => 0);
        $detail = array();
        foreach (array('prior' => 'Past', 'ahead' => 'Future') as $dir => $dir_label) {
            $items = json_decode((string) get_post_meta($r->ID, '_brm_' . $dir . '_items', true), true);
            if (!is_array($items)) continue;
            foreach ($items as $it) {
                $pid = isset($it['post_id']) ? intval($it['post_id']) : 0;
                if (!$pid || !get_post($pid)) continue;
                $d = brmwa_item_date($pid);
                if ($d === '') { $class = 'neither'; }
                else {
                    $in_old = $d >= $win['old'][$dir][0] && $d <= $win['old'][$dir][1];
                    $in_new = $d >= $win['new'][$dir][0] && $d <= $win['new'][$dir][1];
                    $class = $in_old && $in_new ? 'both' : ($in_old ? 'old_only' : ($in_new ? 'new_only' : 'neither'));
                }
                $counts[$class]++;
                if ($class === 'old_only' || $class === 'neither') {
                    $detail[] = array('dir' => $dir_label, 'class' => $class, 'id' => $pid, 'title' => get_the_title($pid), 'type' => get_post_type($pid), 'date' => $d);
                }
            }
        }
        $groups = wp_get_post_terms($r->ID, 'group', array('fields' => 'names'));
        $all[] = array('id' => $r->ID, 'title' => $r->post_title, 'key' => $key, 'group' => $groups ? implode(', ', $groups) : '—', 'groups' => $groups ?: array(),
            'period' => $periods[$key][0] . ' ' . $year, 'win' => $win, 'counts' => $counts, 'detail' => $detail, 'year' => $year,
            'sort' => $year * 100 + $periods[$key][1]);
    }

    // Option lists come from all reports, before filtering
    $years = array_unique(wp_list_pluck($all, 'year')); rsort($years);
    $group_names = array();
    foreach ($all as $row) foreach ($row['groups'] as $g) $group_names[$g] = $g;
    ksort($group_names);

    // Filter
    $rows = array_values(array_filter($all, function ($row) use ($f_year, $f_period, $f_group, $f_status, $f_q) {
        if ($f_year && $row['year'] !== $f_year) return false;
        if ($f_period && $row['key'] !== $f_period) return false;
        if ($f_group !== '' && !in_array($f_group, $row['groups'], true)) return false;
        if ($f_q !== '' && stripos($row['title'] . ' ' . $row['group'], $f_q) === false) return false;
        $c = $row['counts'];
        switch ($f_status) {
            case 'old_only': return $c['old_only'] > 0;
            case 'neither':  return $c['neither'] > 0;
            case 'issues':   return $c['old_only'] > 0 || $c['neither'] > 0;
            case 'clean':    return $c['old_only'] === 0 && $c['neither'] === 0;
        }
        return true;
    }));

    // Sort (ties: newest period, then group)
    $dir = $order === 'asc' ? 1 : -1;
    usort($rows, function ($a, $b) use ($orderby, $dir) {
        switch ($orderby) {
            case 'report': $cmp = strcasecmp($a['title'], $b['title']); break;
            case 'group':  $cmp = strcasecmp($a['group'], $b['group']); break;
            case 'period': $cmp = $a['sort'] <=> $b['sort']; break;
            default:       $cmp = $a['counts'][$orderby] <=> $b['counts'][$orderby];
        }
        if ($cmp) return $cmp * $dir;
        return ($b['sort'] <=> $a['sort']) ?: strcasecmp($a['group'], $b['group']);
    });

    $tot = array('both' => 0, 'old_only' => 0, 'new_only' => 0, 'neither' => 0);
    foreach ($rows as $row) foreach ($tot as $k => $_) $tot[$k] += $row['counts'][$k];

    $fmt = function ($w) { return esc_html(date('M j', strtotime($w[0])) . ' – ' . date('M j, Y', strtotime($w[1]))); };
    $base_args = array('page' => 'brm-window-audit', 'yr' => $f_year ?: null, 'per' => $f_period ?: null, 'grp' => $f_group !== '' ? $f_group : null, 'status' => $f_status ?: null, 'q' => $f_q !== '' ? $f_q : null);
    $th = function ($col, $label) use ($base_args, $orderby, $order) {
        $is = $orderby === $col;
        $next = $is && $order === 'asc' ? 'desc' : ($is ? 'asc' : (in_array($col, array('report', 'group'), true) ? 'asc' : 'desc'));
        $url = add_query_arg(array_merge($base_args, array('orderby' => $col, 'order' => $next)), admin_url('admin.php'));
        return '<th><a href="' . esc_url($url) . '">' . esc_html($label) . ($is ? ($order === 'asc' ? ' ▲' : ' ▼') : '') . '</a></th>';
    };
    $statuses = array('' => 'All reports', 'issues' => 'Needs attention (old-only or neither)', 'old_only' => 'Has old-only items', 'neither' => 'Has neither-window items', 'clean' => 'No issues');
    ?>
    <div class="wrap">
        <h1>Bimonthly Window Audit</h1>
        <p>Read-only. Compares each saved report item against the <strong>old</strong> (2.3.4) and <strong>new</strong> (2.3.6) Past/Future windows.
           Saved items always still display and export. <strong>Old only</strong> items would no longer be offered by the "Add Item" picker if the report is edited.</p>

        <form method="get" style="margin:12px 0;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <input type="hidden" name="page" value="brm-window-audit">
            <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
            <select name="yr">
                <option value="0">All years</option>
                <?php foreach ($years as $y) printf('<option value="%d"%s>%d</option>', $y, selected($f_year, $y, false), $y); ?>
            </select>
            <select name="per">
                <option value="">All periods</option>
                <?php foreach ($periods as $k => $p) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($f_period, $k, false), esc_html($p[0])); ?>
            </select>
            <select name="grp">
                <option value="">All groups</option>
                <?php foreach ($group_names as $g) printf('<option value="%s"%s>%s</option>', esc_attr($g), selected($f_group, $g, false), esc_html($g)); ?>
            </select>
            <select name="status">
                <?php foreach ($statuses as $k => $label) printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($f_status, $k, false), esc_html($label)); ?>
            </select>
            <input type="search" name="q" value="<?php echo esc_attr($f_q); ?>" placeholder="Search report or group">
            <button class="button button-primary">Apply</button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=brm-window-audit')); ?>">Reset</a>
        </form>

        <p><strong><?php echo count($rows); ?></strong> of <?php echo count($all); ?> reports shown.</p>

        <table class="widefat striped" style="max-width:640px;margin-bottom:18px;">
            <thead><tr><th>Items in shown reports</th><th>Items</th></tr></thead>
            <tbody>
                <tr><td>In both windows (unaffected)</td><td><?php echo intval($tot['both']); ?></td></tr>
                <tr><td><strong>Old window only</strong> (not offered by picker on edit)</td><td><strong><?php echo intval($tot['old_only']); ?></strong></td></tr>
                <tr><td>New window only</td><td><?php echo intval($tot['new_only']); ?></td></tr>
                <tr><td>Neither window</td><td><?php echo intval($tot['neither']); ?></td></tr>
            </tbody>
        </table>
        <?php if ($no_period) : ?>
            <p><em><?php echo intval($no_period); ?> report(s) have no period/year set and are skipped (the picker uses a "today ± 2 months" fallback for those).</em></p>
        <?php endif; ?>

        <table class="widefat striped">
            <thead><tr>
                <?php echo $th('report', 'Report'), $th('group', 'Group'), $th('period', 'Period'); ?>
                <th>Old Past / Future</th><th>New Past / Future</th>
                <?php echo $th('both', 'Both'), $th('old_only', 'Old only'), $th('new_only', 'New only'), $th('neither', 'Neither'); ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=bimonthly-report-manager')); ?>"><?php echo esc_html($row['title']); ?></a> <small>#<?php echo intval($row['id']); ?></small>
                        <?php if ($row['detail']) : ?>
                            <details style="margin-top:4px;"><summary>Show <?php echo count($row['detail']); ?> item(s)</summary>
                                <ul style="margin:6px 0 0 16px;list-style:disc;">
                                <?php foreach ($row['detail'] as $d) : ?>
                                    <li><?php echo $d['class'] === 'old_only' ? '<strong>old only</strong>' : 'neither'; ?> · <?php echo esc_html($d['dir']); ?> ·
                                        <a href="<?php echo esc_url(get_edit_post_link($d['id'])); ?>"><?php echo esc_html($d['title'] ?: '(no title)'); ?></a>
                                        <small>(<?php echo esc_html($d['type']); ?>, <?php echo esc_html($d['date'] ?: 'no date'); ?>)</small></li>
                                <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($row['group']); ?></td>
                    <td><?php echo esc_html($row['period']); ?></td>
                    <td><?php echo $fmt($row['win']['old']['prior']); ?><br><?php echo $fmt($row['win']['old']['ahead']); ?></td>
                    <td><?php echo $fmt($row['win']['new']['prior']); ?><br><?php echo $fmt($row['win']['new']['ahead']); ?></td>
                    <td><?php echo intval($row['counts']['both']); ?></td>
                    <td><?php echo $row['counts']['old_only'] ? '<strong>' . intval($row['counts']['old_only']) . '</strong>' : 0; ?></td>
                    <td><?php echo intval($row['counts']['new_only']); ?></td>
                    <td><?php echo intval($row['counts']['neither']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows) : ?><tr><td colspan="9">No reports match.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
