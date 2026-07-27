#!/usr/bin/env python3
"""
Bimonthly Update PDF Generator
Generates branded narrative-style PDF exports.

Usage: python3 generate-pdf.py <input.json> <output.pdf>

Admin cover:    Network Logo centered
Center cover:   Network Logo + "[Center Name] – Bi-Monthly Update" below

Body is narrative-style (not tabular).
"""

import json
import sys
import os
from reportlab.lib.pagesizes import letter
from reportlab.lib.units import inch
from reportlab.lib.colors import HexColor
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, PageBreak,
    Image, HRFlowable
)
from reportlab.lib.enums import TA_CENTER, TA_LEFT


def build_styles():
    """Create custom paragraph styles for the PDF."""
    styles = getSampleStyleSheet()

    styles.add(ParagraphStyle(
        'CoverTitle',
        parent=styles['Title'],
        fontSize=28,
        leading=34,
        alignment=TA_CENTER,
        textColor=HexColor('#1a3a5c'),
        spaceAfter=12,
    ))

    styles.add(ParagraphStyle(
        'CoverSubtitle',
        parent=styles['Normal'],
        fontSize=16,
        leading=20,
        alignment=TA_CENTER,
        textColor=HexColor('#4a6a8a'),
        spaceAfter=6,
    ))

    styles.add(ParagraphStyle(
        'SectionHeading',
        parent=styles['Heading1'],
        fontSize=18,
        leading=24,
        textColor=HexColor('#1a3a5c'),
        spaceBefore=20,
        spaceAfter=12,
        borderWidth=0,
    ))

    styles.add(ParagraphStyle(
        'ItemTitle',
        parent=styles['Heading3'],
        fontSize=12,
        leading=16,
        textColor=HexColor('#2c3e50'),
        spaceBefore=14,
        spaceAfter=4,
        fontName='Helvetica-Bold',
    ))

    styles.add(ParagraphStyle(
        'ItemMeta',
        parent=styles['Normal'],
        fontSize=9,
        leading=13,
        textColor=HexColor('#7f8c8d'),
        spaceAfter=4,
    ))

    styles.add(ParagraphStyle(
        'ItemBody',
        parent=styles['Normal'],
        fontSize=10,
        leading=14,
        textColor=HexColor('#2c3e50'),
        spaceAfter=8,
    ))

    styles.add(ParagraphStyle(
        'OutputLabel',
        parent=styles['Normal'],
        fontSize=9,
        leading=13,
        textColor=HexColor('#1a3a5c'),
        fontName='Helvetica-BoldOblique',
        spaceAfter=2,
    ))

    styles.add(ParagraphStyle(
        'OutputDesc',
        parent=styles['Normal'],
        fontSize=10,
        leading=14,
        textColor=HexColor('#34495e'),
        leftIndent=12,
        spaceAfter=10,
    ))

    return styles


def build_cover_page(data, styles):
    """Build cover page elements."""
    elements = []

    # Spacer to push content down
    elements.append(Spacer(1, 2 * inch))

    # Logo (if exists)
    logo_path = data.get('logo_path', '')
    if logo_path and os.path.exists(logo_path):
        img = Image(logo_path)
        # Scale proportionally, max 3 inches wide
        aspect = img.imageWidth / img.imageHeight
        max_w = 3 * inch
        max_h = 1.5 * inch
        if img.imageWidth > max_w:
            img.drawWidth = max_w
            img.drawHeight = max_w / aspect
        if img.drawHeight > max_h:
            img.drawHeight = max_h
            img.drawWidth = max_h * aspect
        img.hAlign = 'CENTER'
        elements.append(img)
        elements.append(Spacer(1, 0.5 * inch))

    # Network title (from site title)
    network_title = data.get('network_title', 'Bi-Monthly Update')
    elements.append(Paragraph(network_title, styles['CoverTitle']))

    # Subhead: center name (group taxonomy)
    group_name = data.get('group_name', '')
    if group_name:
        elements.append(Paragraph(
            '{0} &ndash; Bi-Monthly Update'.format(group_name),
            styles['CoverSubtitle']
        ))
    else:
        elements.append(Paragraph('Bi-Monthly Update', styles['CoverSubtitle']))

    # Update title (the post title, e.g. "March-April 2026")
    elements.append(Spacer(1, 0.3 * inch))
    elements.append(Paragraph(data.get('title', ''), styles['CoverSubtitle']))

    elements.append(PageBreak())
    return elements


def build_section(section_label, items, styles, show_type=True, show_author=True, show_date=True):
    """Build a narrative-style section for a list of items."""
    elements = []

    if not items:
        return elements

    elements.append(Paragraph(section_label, styles['SectionHeading']))

    elements.append(HRFlowable(
        width='100%', thickness=1,
        color=HexColor('#bdc3c7'),
        spaceAfter=10
    ))

    for item in items:
        # Title line
        post_title = item.get('post_title', 'Untitled')
        post_type_label = item.get('post_type_label', '')
        elements.append(Paragraph(
            '{0}'.format(post_title),
            styles['ItemTitle']
        ))

        # Meta line: conditionally show Type | Author | Date
        meta_parts = []
        if show_type and post_type_label:
            meta_parts.append(post_type_label)
        author = item.get('author', '')
        if show_author and author:
            meta_parts.append('by {0}'.format(author))
        date = item.get('date', '')
        if show_date and date:
            meta_parts.append(date)

        if meta_parts:
            elements.append(Paragraph(
                ' &bull; '.join(meta_parts),
                styles['ItemMeta']
            ))

        # Summary
        summary = item.get('summary', '')
        if summary:
            elements.append(Paragraph(summary, styles['ItemBody']))

        # Associated Workplan Outputs (now an array)
        outputs = item.get('outputs', [])

        # Backwards compatibility: check old flat fields
        if not outputs:
            output_desc = item.get('output_desc', '')
            output_letter = item.get('output_letter', '')
            if output_desc:
                outputs = [{'output_letter': output_letter, 'output_desc': output_desc}]

        if outputs:
            label = 'Associated Workplan Output:' if len(outputs) == 1 else 'Associated Workplan Outputs:'
            elements.append(Paragraph(label, styles['OutputLabel']))

            for o in outputs:
                od = o.get('output_desc', '')
                if od:
                    # Build full path: GoalLetter.ObjectiveNumber.OutputLetter.
                    path_parts = []
                    gl = o.get('goal_letter', '')
                    on = o.get('objective_number', '')
                    ol = o.get('output_letter', '')
                    if gl: path_parts.append(str(gl))
                    if on: path_parts.append(str(on))
                    if ol: path_parts.append(str(ol))
                    path = '.'.join(path_parts) + '.' if path_parts else ''
                    txt = '{0} {1}'.format(path, od) if path else od
                    elements.append(Paragraph(txt, styles['OutputDesc']))

        # Separator between items
        elements.append(Spacer(1, 4))
        elements.append(HRFlowable(
            width='60%', thickness=0.5,
            color=HexColor('#ecf0f1'),
            spaceAfter=4,
            hAlign='LEFT'
        ))

    return elements


def build_meta_cover_page(data, styles):
    """Build cover page for meta-report."""
    elements = []
    elements.append(Spacer(1, 2 * inch))

    logo_path = data.get('logo_path', '')
    if logo_path and os.path.exists(logo_path):
        img = Image(logo_path)
        aspect = img.imageWidth / img.imageHeight
        max_w = 3 * inch
        max_h = 1.5 * inch
        if img.imageWidth > max_w:
            img.drawWidth = max_w
            img.drawHeight = max_w / aspect
        if img.drawHeight > max_h:
            img.drawHeight = max_h
            img.drawWidth = max_h * aspect
        img.hAlign = 'CENTER'
        elements.append(img)
        elements.append(Spacer(1, 0.5 * inch))

    network_title = data.get('network_title', 'Bi-Monthly Update')
    elements.append(Paragraph(network_title, styles['CoverTitle']))
    elements.append(Paragraph('Bi-Monthly Network Update', styles['CoverSubtitle']))

    elements.append(PageBreak())
    return elements


def build_table_of_contents(updates, styles):
    """Build a table of contents page listing each center with internal links."""
    elements = []

    elements.append(Paragraph('Table of Contents', styles['SectionHeading']))
    elements.append(HRFlowable(
        width='100%', thickness=1,
        color=HexColor('#bdc3c7'),
        spaceAfter=14
    ))

    toc_style = ParagraphStyle(
        'TOCEntry',
        parent=styles['Normal'],
        fontSize=12,
        leading=20,
        textColor=HexColor('#2c3e50'),
    )

    toc_region_style = ParagraphStyle(
        'TOCRegion',
        parent=styles['Normal'],
        fontSize=10,
        leading=20,
        textColor=HexColor('#7f8c8d'),
    )

    for i, update in enumerate(updates):
        group_name = update.get('group_name', 'Unknown Center')
        region = update.get('region', 99)
        region_label = 'Region {0}'.format(region) if region < 99 else ''

        anchor_name = 'center_{0}'.format(i)
        entry_text = '<a href="#{0}" color="#2271b1">{1}</a>'.format(anchor_name, group_name)
        if region_label:
            entry_text += '  <font size="9" color="#7f8c8d">({0})</font>'.format(region_label)

        elements.append(Paragraph(entry_text, toc_style))

    elements.append(PageBreak())
    return elements


def generate_pdf(input_path, output_path):
    """Main PDF generation function."""
    with open(input_path, 'r') as f:
        data = json.load(f)

    styles = build_styles()

    doc = SimpleDocTemplate(
        output_path,
        pagesize=letter,
        leftMargin=0.75 * inch,
        rightMargin=0.75 * inch,
        topMargin=0.75 * inch,
        bottomMargin=0.75 * inch,
    )

    story = []

    if data.get('is_meta'):
        # Display options
        show_type = data.get('show_type', 1)
        show_author = data.get('show_author', 1)
        show_date = data.get('show_date', 1)

        # Meta-report mode
        story.extend(build_meta_cover_page(data, styles))

        updates = data.get('updates', [])

        # Table of contents
        story.extend(build_table_of_contents(updates, styles))

        # Each center's update on its own page(s)
        for i, update in enumerate(updates):
            group_name = update.get('group_name', 'Unknown Center')
            region = update.get('region', 99)
            region_label = 'Region {0}'.format(region) if region < 99 else ''

            # Bookmark anchor for TOC link
            anchor_name = 'center_{0}'.format(i)
            story.append(Paragraph('<a name="{0}"/>{1}'.format(anchor_name, ''), styles['Normal']))

            # Center heading
            heading = group_name
            if region_label:
                heading += '  <font size="12" color="#7f8c8d">({0})</font>'.format(region_label)

            story.append(Paragraph(heading, styles['SectionHeading']))
            story.append(HRFlowable(
                width='100%', thickness=2,
                color=HexColor('#1a3a5c'),
                spaceAfter=16
            ))

            # Prior items
            prior = update.get('prior_items', [])
            story.extend(build_section('Past Highlights', prior, styles, show_type, show_author, show_date))

            # Ahead items
            ahead = update.get('ahead_items', [])
            if prior and ahead:
                story.append(Spacer(1, 12))
            story.extend(build_section('Future Highlights', ahead, styles, show_type, show_author, show_date))

            # Page break between centers (not after last)
            if i < len(updates) - 1:
                story.append(PageBreak())

    else:
        # Single update mode
        story.extend(build_cover_page(data, styles))

        prior_items = data.get('prior_items', [])
        story.extend(build_section('Past Highlights', prior_items, styles))

        ahead_items = data.get('ahead_items', [])
        if prior_items and ahead_items:
            story.append(PageBreak())

        story.extend(build_section('Future Highlights', ahead_items, styles))

    doc.build(story)


if __name__ == '__main__':
    if len(sys.argv) != 3:
        print('Usage: python3 generate-pdf.py <input.json> <output.pdf>')
        sys.exit(1)

    generate_pdf(sys.argv[1], sys.argv[2])
