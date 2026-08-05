# Content brief template: destination guide (for external generation)

Purpose: Claude produces this brief per destination; the Hebrew prose is generated
externally (owner's ChatGPT workflow) and returned for validation + publishing through
the site's fail-closed publication contract. Claude never authors the article prose.

## How to use
1. Claude fills every bracketed section below for the target destination, including the
   verified source list (real URLs actually checked via web search, never invented).
2. Owner pastes the completed brief into ChatGPT (or preferred tool) with the
   instruction block at the bottom.
3. Returned Hebrew text goes back to Claude, who validates it against the checklist,
   fixes structure only (headings, tables markup, forbidden characters), provisions it
   to WordPress as a draft, and reports contract status honestly.

---

## BRIEF: [DESTINATION NAME IN HEBREW] ([map_state])

### Hard requirements (the publication contract will reject anything less)
- 5,000+ words of visible Hebrew text (75%+ Hebrew words)
- 12+ H2 sections
- 3+ real decision tables (comparison tables a traveler uses to decide)
- Original writing synthesized from the sources below; never verbatim translation of
  a single source; no other site's structure copied wholesale
- FORBIDDEN: em dash, en dash, invented prices, invented statistics, availability
  claims, operator recommendations phrased as guarantees, superlative rank claims
- Kosher/community section must say to verify hours/availability with local providers
- Every fact that could change (prices, schedules, entry rules) phrased as needing
  a fresh check, not asserted as permanent truth

### H2 map (Claude fills, ~14 sections)
1. [למה {destination} — the honest pitch, who it fits, who it does not]
2. [מתי לטוס — seasons, crowds, weather; TABLE: month-by-month]
3. [איפה לישון — neighborhoods; TABLE: area comparison]
4. [איך מגיעים מהשדה — TABLE: transfer options]
5. [תחבורה בעיר]
6. [מסלול 3 ימים]
7. [מסלול 5 ימים]
8. [עם ילדים]
9. [כשרות וקהילה יהודית]
10. [אוכל מקומי]
11. [טיולי יום]
12. [כניסה לישראלים, מטבע, טיפים]
13. [נגישות]
14. [שאלות נפוצות — 5 questions minimum]

### Entities to weave in naturally (15+ per 1,000 words where sensible)
[Claude lists: airports (codes), districts, landmarks, transit systems, seasons,
currencies, institutions — the entity graph AI citation rewards]

### Verified sources (Claude fills via real web search; 10+ required)
| # | Title | URL | Publisher | Checked |
|---|---|---|---|---|
| 1 | [real] | [real] | [real] | [date Claude verified it] |

### Instruction block for the generation tool
Write in natural, confident, warm Hebrew for Israeli travelers. RTL. No dashes of any
kind (use commas or periods). No prices or numbers you were not given. Follow the H2
map exactly, hit the word count, produce the three tables as HTML `<table>` markup,
and end the final section with exactly this sentence:
`המחיר, הזמינות והתנאים מאומתים לפני התשלום.`
