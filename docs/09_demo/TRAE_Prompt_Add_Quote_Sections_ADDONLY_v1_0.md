# TRAE PROMPT — Add Quote Sections (ADD-ONLY v1.0)

clear

ADD-ONLY. No refactors. No renames.

------------------------------------------
GOAL
------------------------------------------

Introduce Section as grouping container above Quote Blocks.

------------------------------------------
DOMAIN
------------------------------------------

Add new entity: QuoteSection

Fields:
- id
- quote_id
- name (default: "New Section")
- order_index
- show_total_value (bool)
- show_item_count (bool)
- show_total_hours (bool)

Blocks reference section_id (nullable).

Sections are NOT nestable.

------------------------------------------
RULES
------------------------------------------

1. Section total derived from blocks inside it.
2. If section contains recurring + once-off:
   - Display separate totals.
3. Adjustment behavior:
   - If adjustment in section with content → applies to section.
   - If adjustment in section with nothing else → applies to quote.
4. Cannot delete section with blocks inside.
5. Cloning section clones all blocks beneath it.
6. Sections reorderable.
7. Blocks reorderable inside section.
8. Blocks draggable between sections.

------------------------------------------
UI
------------------------------------------

Add flyout entry:
- Add Section

Section header:
- Full-width black bar
- Inline editable title
- Toggle dropdown for display options
- Derived totals displayed beneath title

------------------------------------------
ACCEPTANCE
------------------------------------------

Sections do not affect delivery topology.

------------------------------------------
TESTS
------------------------------------------

1. Section ordering persists.
2. Block move between sections persists.
3. Section totals derive correctly.
4. Adjustment scope rule enforced.
5. Section clone duplicates blocks.
6. Cannot delete section with blocks inside.

Return:
- Files created
- Migrations added
- Summary of changes
- Verification steps

