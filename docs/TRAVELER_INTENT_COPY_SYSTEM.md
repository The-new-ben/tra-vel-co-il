# Tra-Vel traveler-intent copy system

Research date: 2026-07-18
Applies to: Tra-Vel V2 public WordPress theme, map, AI planner, results, handoff, checkout, saved trips, and trip cockpit
Status: implementation contract

## Executive rule

Tra-Vel must sell the trip, not describe the machine.

The traveler should see familiar products, a clear price scope, useful proof, and one obvious next action. Product architecture, orchestration, adapters, revisions, events, and internal decision models may exist underneath, but they are not the value proposition.

Every public surface must answer four questions in this order:

1. What can I get here?
2. Is it relevant to my trip and budget?
3. What does the price include, and who provides it?
4. What happens when I press the primary button?

The language formula is:

> Traveler desire + concrete travel product + proof + exact next action

Example:

> חופשה זוגית בבודפשט, 4 לילות, טיסה ומלון, מחיר מלא לזוג. בדקו מחיר עדכני.

## What the Israeli market teaches

This review samples public homepage language. It is not a trademark, visual-identity, or content-copying brief. Tra-Vel must use original wording and a distinct design.

| Brand | Sampled public wording | What works | What Tra-Vel should improve |
| --- | --- | --- | --- |
| ISSTA | `חיפוש חופשה`, `דילים חמים באיסתא` | Familiar category language, visible inventory, dates, party composition, and prices | Show the full party and trip cost, not only an attractive per-person entry price. Connect each deal to route, conditions, map, and follow-on products. |
| Travelist | `לאן טסים?`, `השוואת מחירי טיסות לחו"ל`, `חפש טיסה` | The question, product, and action are immediately clear | Extend comparison beyond the fare into baggage, transfers, accommodation, insurance, seller, and total trip cost. |
| Eshet Tours | `איזו חופשה בא לכם?`, `מצאו לי מלון בארץ` | Conversational question followed by a specific product action | Preserve the simplicity while offering budget-first, vibe-first, and whole-trip paths. |
| Gulliver | `בחר סוג חופשה`, `חפש` | Familiar category choice and low-language search controls | Replace generic `חפש` when the action can be more specific, such as `השוו טיסות` or `בדקו מחיר מלא`. |
| Ophir Tours | `גמיש? לא משנה לי לאן` | Flexible destination intent appears inside the buying flow | Make flexibility a first-class discovery mode with budget, dates, climate, duration, and route filters on the Earth. |
| Secret Flights | `החופשה שלך מתחילה עכשיו`, `לאן טסים?`, `חיפוש` | Immediate travel framing, flexible dates, alerts, and price-led discovery | Turn a cheap flight into a realistic complete-trip proposal, including the destination cost after landing. |

Sources: [ISSTA](https://www.issta.co.il/), [Travelist](https://www.travelist.co.il/), [Eshet Tours](https://www.eshet.com/), [Gulliver](https://www.gulliver.co.il/), [Ophir Tours](https://www.ophirtours.co.il/), [Secret Flights](https://secretflights.co.il/).

### Market conclusion

The strongest local pattern is not sophisticated language. It is immediate recognition:

1. A traveler question: where, when, with whom, or what kind of holiday.
2. A plain product noun: flights, hotels, packages, insurance, cruises, or organized tours.
3. A direct action: search, compare, check a price, save, or continue.
4. Deal evidence: destination, dates, travelers, inclusions, and price.
5. A return reason: price alert, last-minute inventory, membership, or saved trip.

Tra-Vel should preserve that familiarity and add what the sampled competitors usually leave fragmented: one complete trip price, route trade-offs, spatial planning, live revalidation, and a persistent trip cockpit.

## Three language layers

### 1. Internal product language

Use in code, architecture, operations, and technical documentation only:

- orchestration
- decision model, decision layer, decision kernel
- supplier adapter
- event, state, revision, run, provenance
- workspace synchronization
- quote-case lifecycle
- 360-degree coverage model
- AI reasoning or tool execution

### 2. Traveler-facing language

Use in the public theme:

- חופשה
- טיסה
- מלון
- טיסה ומלון
- המחיר המלא
- מה כלול
- מי מוכר ומי נותן שירות
- השוו אפשרויות
- בדקו מחיר עדכני
- שמרו לנסיעה
- בקשו עזרה
- ההזמנות והמסלול שלכם

### 3. Trust and transaction language

Use close to price, commitment, handoff, and payment:

- price scope: per traveler, per room, per party, or total trip
- included and excluded items
- seller and service provider
- verification time
- cancellation and change terms
- whether the next click checks availability, opens a supplier, reserves, or charges
- what happens if availability or price changes

The trust layer must never be shortened to create artificial urgency.

## Public-language exclusion list

Do not expose these terms in navigation, hero copy, product cards, map cards, or sales CTAs:

- Intelligence
- orchestration
- decision system
- decision layer
- decision kernel
- 360 operating system
- module
- framework
- adapter
- event
- revision
- run
- state machine
- provenance
- current repository
- source event
- commercial module
- backend

`AI` is permitted only when it sets a useful expectation. It should be a supporting label, not a substitute for the outcome. Prefer `מתכנן החופשה` over `מערכת AI חכמה`, and prefer `ספרו לנו מה אתם מחפשים` over `הפעילו סוכן`.

## Voice and grammar

- Write in natural Israeli Hebrew.
- Use second-person plural consistently so the interface works for individuals, couples, families, and groups.
- Prefer active verbs and concrete nouns.
- Use one short idea per sentence.
- Prefer present tense for current facts and future tense only for the result of the next action.
- Do not use em dashes. Use a full stop, colon, comma, or parentheses.
- Avoid English product jargon when a familiar Hebrew term exists.
- Keep airport codes, airline names, hotel names, and supplier names in their official form.
- Make mixed RTL and LTR values visually unambiguous, especially dates, flight numbers, currencies, and airport codes.
- Do not use superlatives such as `הכי זול`, `הכי טוב`, or `מושלם` without a documented comparison set and calculation.
- Do not use urgency such as `נשארו רק 2` unless it comes from a current supplier response and the time is disclosed where material.
- Do not use `חי`, `עכשיו`, `זמין`, `נשמר`, `הוזמן`, or `אושר` unless the application state proves it.

## Intent-first entry system

The interface should recognize the traveler's starting point instead of forcing every person through the same form.

| Starting intent | Opening question | Primary action | First useful result | Commercial path |
| --- | --- | --- | --- | --- |
| Known destination | `לאן ומתי תרצו לטוס?` | `בדקו מחירים ליעד` | Three relevant flight or package options | Flight, hotel, package, insurance, transfers, activities |
| Known product | `מה תרצו להזמין?` | `השוו טיסות`, `השוו מלונות`, or the selected product | Product-specific comparison | Supplier handoff or checkout |
| Fixed budget | `כמה תרצו להוציא על כל החופשה?` | `הראו חופשות בתקציב` | Destinations that fit the full party budget | Package plus attachments |
| Flexible destination | `פתוחים להצעה?` | `הראו לי לאן אפשר לטוס` | Budget and date-filtered Earth pins | Complete-trip proposal |
| Mood or occasion | `איזו חופשה מתאימה לכם?` | `הראו חופשות שמתאימות` | Three explained proposals | Multi-product itinerary |
| Surprise me | `ספרו תקציב, תאריכים ואווירה במשפט אחד` | `תפתיעו אותי` | A short, editable shortlist | Complete-trip proposal |
| Destination research | `מה חשוב לכם לדעת לפני שסוגרים?` | `פתחו את מדריך היעד` | Answer, evidence, and relevant product action | Guide-to-search conversion |
| Existing booking | `מה תרצו לבדוק בנסיעה שלכם?` | `פתחו את הנסיעה` | Confirmations, alerts, documents, and next action | Servicing and attachments |

## Product shelf

Navigation and landing pages must use familiar product names. Secondary copy may explain Tra-Vel's additional value.

| Product | Public label | Supporting copy | Primary CTA | Required price scope |
| --- | --- | --- | --- | --- |
| Air | `טיסות` | `השוו זמן, כבודה, עצירות ותנאי שינוי` | `השוו טיסות` | Total for selected travelers, plus baggage and payment fees |
| Lodging | `מלונות` | `השוו אזור, חדר, ארוחות וביטול` | `השוו מלונות` | Total stay for selected rooms and guests, including mandatory taxes where available |
| Dynamic package | `טיסה ומלון` | `ראו אם הזמנה יחד חוסכת כסף או סיכון` | `השוו טיסה ומלון` | Total package for the party, with separately stated exclusions |
| Holiday package | `חבילות נופש` | `טיסה, מלון ומה שכלול בחבילה` | `מצאו חבילות` | Total party price and exact occupancy basis |
| Insurance | `ביטוח נסיעות` | `בדקו מה חשוב בכיסוי לפי הנוסעים והפעילויות` | `לפרטי בדיקת ביטוח` | Questions and coverage details to verify; show a premium only after a licensed supplier returns a sourced offer |
| Transfers | `העברות` | `מהשדה למלון ובחזרה` | `בדקו העברה` | Total vehicle or party price, luggage and waiting terms |
| Car hire | `השכרת רכב` | `השוו רכב, ביטוח, פיקדון ומדיניות דלק` | `השוו רכבים` | Full rental total, mandatory charges, deposit, mileage and coverage |
| Activities | `אטרקציות וסיורים` | `מצאו מה לעשות ליד המסלול שלכם` | `ראו פעילויות` | Total tickets for party, cancellation and meeting point |
| Connectivity | `eSIM ותקשורת` | `בחרו חבילה לפי המדינות ומשך הנסיעה` | `השוו חבילות גלישה` | Total plan price, data allowance, validity and supported countries |
| Cruises | `הפלגות` | `השוו מסלול, תא, ארוחות ועלויות חובה` | `מצאו הפלגות` | Cabin and party total, port charges, gratuities and exclusions |
| Guided trips | `טיולים מאורגנים` | `מסלול, מדריך, קבוצה ומה כלול` | `מצאו טיול מאורגן` | Full traveler or party price and single-room supplement |
| Domestic | `חופשה בארץ` | `מלונות וחבילות לפי אזור וסגנון` | `מצאו חופשה בארץ` | Room or party total including meal plan and cancellation |

## Current Tra-Vel copy audit and replacements

These are implementation priorities. Proposed copy that promises three options, current prices, availability, or payment must be published only when the corresponding capability is connected and validated.

| Surface | Current wording | Problem | Proposed public wording |
| --- | --- | --- | --- |
| Homepage eyebrow | `Tra-Vel Intelligence · מסע אחד, החלטה אחת בכל פעם` | Internal platform presentation | `מתחילים מהחופשה שמתאימה לכם` |
| Homepage H1 | `כל העולם. על מפה אחת.` | Visually strong but does not state the product outcome | `איזו חופשה מתאימה לכם עכשיו?` |
| Homepage support | Explains choosing the globe and the system's breadth | Starts with the interface instead of the traveler's need | `ספרו יעד, תקציב או סגנון. נשווה טיסות, מלונות והמחיר המלא לחופשה.` |
| Natural-language prompt | `אפשר גם לכתוב:` | Treats conversation as a secondary technical input | `כתבו מה אתם מחפשים` |
| Surprise supporting copy | `הסוכן מתחיל לבנות בקשה` | Describes backend work | `ספרו תקציב ואווירה. אנחנו נציע חופשות שמתאימות.` |
| Search CTA | `בנו והשוו נסיעה` | Abstract and combines two actions | Product-specific CTA such as `השוו טיסה ומלון` |
| Map card label | `הבחירה שנבדקת עכשיו` | Sounds like project status | `היעד שבחרתם` |
| Map result price | `בדיקה חיה` | Looks like a price but is not a price | `בחרו תאריכים לקבלת מחיר` |
| Map plan brand | `Tra-Vel 360°` | Internal concept placed above the user's goal | `כל החופשה לבנגקוק` |
| Map plan summary | `עכשיו מחברים דרך, לינה, חוויות והגנה` | Describes orchestration | `השוו טיסות, מלונות, מה עושים וביטוח לבנגקוק` |
| Map plan CTA | `פתחו תוכנית 360° מלאה` | Abstract and system-led | `תכננו חופשה לבנגקוק` |
| Route section eyebrow | `אותו יעד, שלוש החלטות שונות` | Abstract framing | `שלוש דרכים להגיע לבנגקוק` |
| Route CTA | `השוואה מלאה במפה` | Does not say what is compared | `השוו מחירים וזמני טיסה` |
| Deals eyebrow | `הזדמנויות עם תנאים ברורים` | Corporate phrasing | `חופשות שכדאי לבדוק` |
| Guide directory label | `ספריית החלטות` | Internal information architecture | `מדריכי יעדים וחופשות` |
| AI planner navigation | `מתכנן AI` | Technology is the product label | `מתכנן החופשה` |
| Saved area | `נסיעות שמורות` | Good, familiar language | Keep. Add exact alert state such as `המחיר השתנה` only when verified. |
| Assistance handoff | `פתיחת Quote Case` or lifecycle terminology | Internal operations | `בקשו עזרה מנציג` or `שלחו את החופשה לבדיקה` depending on the actual service |
| Account run history | `ריצה`, `גרסה`, or `מצב שרת` | Engineering vocabulary | `תוכניות אחרונות`, `עודכנה`, and a traveler action |

## Homepage copy target

### Hero

Eyebrow:

> מתחילים מהחופשה שמתאימה לכם

H1:

> איזו חופשה מתאימה לכם עכשיו?

Support:

> ספרו יעד, תקציב או סגנון. נשווה טיסות, מלונות והמחיר המלא לחופשה.

Primary CTA after structured criteria:

> הראו לי חופשות

Secondary CTA:

> תפתיעו אותי

Natural-language example:

> שבועיים בתאילנד לזוג עד ₪9,000, עם חוף ואוכל כשר

Proof strip:

- `מחיר מלא לכל הנוסעים`
- `כבודה, העברות וביטוח בנפרד`
- `יתרונות וחסרונות לכל אפשרות`
- `המחיר נבדק לפני מעבר לתשלום`

### Search tabs

Tab labels remain product nouns:

- `טיסה ומלון`
- `טיסות`
- `מלונות`
- `חבילות נופש`
- `ביטוח נסיעות`

The submit label changes with the selected product. It never remains a generic `חפש` if a more precise label is possible.

## Earth and map language contract

The Earth is a selection and discovery surface. It must never become a dashboard covered by simultaneous controls.

Each click produces three short layers:

1. Acknowledge what was selected.
2. Show the next useful information that is truly available.
3. Present one primary action that moves the trip forward.

### Recognized destination

Title:

> בנגקוק נבחרה

Supporting copy before dates are known:

> בחרו תאריכים ונוסעים כדי לראות טיסות, מלונות והמחיר המלא.

Primary CTA:

> בדקו מחירים לבנגקוק

Secondary actions:

- `טיסות`
- `מלונות`
- `מה עושים`
- `מדריך לבנגקוק`

### Coordinate without mapped inventory

Title:

> הנקודה נשמרה על המפה

Supporting copy:

> עדיין אין לנו יעד או מחיר מאומת לנקודה הזאת. אפשר לזהות מקום קרוב או לבחור יעד אחר.

Actions:

- `מצאו יעד קרוב`
- `בחרו נקודה אחרת`

Do not imply that twelve areas are complete merely because the coordinate was accepted.

### Airport click

Title:

> נמל התעופה דובאי נבחר

Supporting copy:

> השוו טיסה ישירה, קונקשן מוגן ועצירה בדרך לפי זמן ומחיר מלא.

Primary CTA:

> השוו מסלולים דרך דובאי

### Hotel or area click

Title:

> סוקומוויט נבחרה כאזור לינה

Supporting copy:

> בדקו מחיר לחדרים, זמן נסיעה לאתרים ותנאי ביטול.

Primary CTA:

> השוו מלונות באזור

### Price pin

A price pin is allowed only when its scope can be understood. The accessible label and detail surface must identify:

- product type
- destination
- currency
- per-person, per-room, per-party, or total scope
- travel dates or flexible-date window
- freshness or verification time
- seller when available

A pin must not show a bare `$950` if the traveler cannot determine what it buys.

### Destination reveal and surprise mode

The complete spinning-Earth reveal is reserved for the homepage and the explicit `תפתיעו אותי` journey. It is an interruptible discovery experience, not a forced transition on every page.

1. On the homepage, the Earth may make one short opening rotation, stop on a planning destination, draw the route from Israel, and assemble the editable flight, stay, transfer, activity, insurance-planning, and extras budget.
2. Before preferences are known, describe the result as a starting direction or planning pick. Do not call it the best option for the traveler.
3. After the traveler provides budget, dates, party, or preferences, the reveal may explain why one option fits those selections.
4. On a destination page, focus directly on that destination. Do not run roulette.
5. On flight, hotel, package, saved-trip, and checkout surfaces, preserve the traveler’s current query and selection. Do not replace it with a random destination.
6. On the full map, expose surprise mode as an optional button while keeping free globe exploration available.
7. On an SEO guide, use only a contextual, progressively enhanced map focused on the article destination. The static answer and internal links must remain available without JavaScript or 3D rendering.
8. The reveal must be cancellable, keyboard accessible, reduced-motion compatible, and must never imply that supplier searches or bookings occurred merely because visual components assembled.
9. Automatic homepage reveals have an explicit type. `seasonal` may focus a reviewed destination during a bounded date window; `evergreen` uses neutral discovery language and a stable daily rotation across supported destinations rather than permanently favoring one place; `surprise` is reserved for a deliberate traveler action.
10. Before live availability and traveler preferences are known, the assembled result is a useful, editable starting direction. “Best,” “cheapest,” “booked,” and similar outcome claims require validated provider evidence and the relevant traveler inputs.
11. The interface may build the itinerary in visible stages, but every stage represents a local selection or confirmed response. Animation must not impersonate supplier work, payment, reservation, or completion.

## AI planner language contract

The AI experience should feel like asking a skilled travel agent, not operating a model.

### Entry

Title:

> ספרו לנו איזו חופשה אתם רוצים

Supporting copy:

> אפשר לכתוב או לדבר בחופשיות. יעד, תקציב, תאריכים, נוסעים ומה חשוב לכם.

Prompt example:

> חופשת ירח דבש אקזוטית לזוג בספטמבר, עד ₪12,000 לכל החופשה

Primary CTA:

> הכינו לי הצעות

Microphone accessible label:

> התחילו להקליט את בקשת החופשה

### Truthful progress

Allowed progress messages:

- `הבקשה התקבלה`
- `מזהים יעד, תאריכים, נוסעים ותקציב`
- `חסר פרט אחד כדי להמשיך`
- `מכינים אפשרויות לפי הפרטים שאישרתם`
- `בודקים מחיר וזמינות מול הספק`
- `שלוש אפשרויות מוכנות להשוואה`
- `המחיר השתנה בזמן הבדיקה`
- `לא התקבל מחיר מהספק. אפשר לנסות תאריכים קרובים`

Disallowed messages without evidence:

- `הסוכן רץ בכל האינטרנט`
- `סוגרים לכם את החופשה`
- `המלון נשמר`
- `הטיסה הוזמנה`
- `מצאנו את המחיר הזול ביותר`
- `כל הספקים נבדקו`

### Clarification

Ask one high-value question at a time. Explain why only if necessary.

Good:

> התקציב הוא לכל הנוסעים יחד או לאדם?

Avoid:

> אנא השלימו את כל השדות החסרים בטופס.

### Proposal shortlist

Heading:

> 3 חופשות שמתאימות למה שביקשתם

Each proposal needs:

- a plain reason it fits
- full party price or a clear unavailable state
- travel dates and duration
- flights and baggage
- hotel, room, board, and location
- transfers and relevant attachments
- major compromise
- seller and verification state

Card CTA before revalidation:

> בדקו את המחיר עכשיו

Card CTA after successful revalidation:

> המשיכו להצעה

## Offer and payment language

### Planning price and final quotation

Tra-Vel must not become visually empty when a live supplier is not connected. A configured planning catalog may show destination names, example routes, example stays, comparison scores, component budgets, and numeric planning prices. These figures support discovery and editing. They are not current inventory and must not unlock a supplier checkout.

The public presentation uses this compact boundary beside each planning total:

> מחיר לתכנון. המחיר, הזמינות והתנאים הסופיים יינתנו לאחר בדיקה מחדש, לפני הרכישה.

The primary action is:

> קבלו הצעה סופית

Rules:

1. Planning prices remain populated and useful. Do not replace every amount or product identity with `לא זמין`, `טרם נבדק`, or an empty card.
2. Planning cards may explain route, lodging, inclusions, trade-offs, and a computed total, but their `data_mode` remains `demo`, `editorial`, or `mixed`.
3. A planning amount is not stored as a confirmed watch price, does not create a savings claim, and cannot open supplier checkout.
4. A final supplier price requires validated live provenance, price scope, currency, retrieval time, availability time, provider identity, and an explicit bookable or purchasable capability.
5. Insurance remains stricter. No insurance premium or product sale appears without a licensed live provider and an explicit regulated-sale capability.
6. The qualification is concise and reassuring. It belongs next to the amount and CTA, not in a blocking warning or construction-state message.

The CTA label must describe the next system boundary.

| Actual next action | Primary CTA | Required adjacent proof | Never say |
| --- | --- | --- | --- |
| Open product search | `השוו טיסות` | Criteria being transferred | `הזמינו עכשיו` |
| Request supplier prices | `בדקו מחיר וזמינות` | What is being checked | `מחיר סופי` |
| Revalidate one offer | `בדקו את המחיר עכשיו` | Last known price and time | `שמרו את המחיר` |
| Open affiliate supplier | `המשיכו ל-[supplier]` | Seller, support owner, transfer disclosure, and whether the destination is a result page or verified checkout | `המשיכו לתשלום` unless the destination is verified as checkout |
| Open Tra-Vel checkout | `המשיכו לתשלום מאובטח` | Merchant, exact total, cancellation terms | Generic `המשך` |
| Charge now | `שלמו ₪X ואשרו הזמנה` | Exact charge, currency, merchant, terms | `אישור` |
| Create assisted request | `שלחו את החופשה לבדיקה` | Response expectation and what data is shared | `הזמינו` |
| Save locally or to account | `שמרו לנסיעה` | Saved-state confirmation | `המחיר נשמר` |

Clear button intent, one visually dominant primary action, and explicit payment consequences reduce uncertainty. Baymard's research specifically recommends descriptive microcopy and clarity about whether a payment button finalizes an order. [Baymard button research](https://baymard.com/learn/button-design).

## Error, empty, and changed-state copy

Every problem message contains:

1. What happened.
2. What remains safe or unchanged.
3. The best next action.

### No live offer

> לא מצאנו מחיר זמין לתאריכים האלה. הנסיעה ששמרתם לא השתנתה.

Primary action:

> הראו תאריכים קרובים

### Price changed

> המחיר עלה ב-₪240 מאז הבדיקה הקודמת. בדקו את המחיר החדש לפני שתמשיכו.

Primary action:

> בדקו את ההצעה המעודכנת

### Supplier timeout

> הספק לא ענה בזמן. לא בוצעה הזמנה ולא נגבה תשלום.

Primary action:

> נסו שוב

Secondary action:

> בדקו אפשרות אחרת

### Session expired

> החיבור לחשבון פג. הנסיעות שכבר נשמרו לא נמחקו.

Primary action:

> התחברו מחדש

### Assisted request conflict

> הבקשה עודכנה במקום אחר. טענו את העדכון האחרון לפני שתמשיכו.

Primary action:

> טענו את העדכון האחרון

## Saved trips and cockpit

The account area may expose more operational detail than a sales page, but it must still use traveler language.

Preferred section labels:

- `הנסיעות שלי`
- `תוכניות אחרונות`
- `מחירים במעקב`
- `בדיקה אישית`
- `הצעות ועדכונים`
- `הזמנות ומסמכים`
- `שינויים והתראות`
- `מה עושים עכשיו`

Preferred status pattern:

> [What changed] + [when] + [what the traveler can do]

Examples:

- `המחיר ירד ב-₪180. נבדק לפני 12 דקות. בדקו את ההצעה.`
- `הטיסה שונתה על ידי חברת התעופה. ראו את השעה החדשה.`
- `הצעת הנציג מוכנה. בדקו מחיר, תנאים ומי נותן שירות.`
- `הבקשה בוטלה. התוכנית נשארה בחשבון ואפשר לעדכן אותה.`

Do not show `revision 4`, `run completed`, `terminal state`, or `server update` unless a support or diagnostic view explicitly requires it.

## SEO and AEO language rules

Public search pages and guides must begin with the searcher's question, not Tra-Vel's product philosophy.

### Page-title patterns

- `טיסות לבנגקוק: מחירים, זמן טיסה וכבודה`
- `חבילות נופש לבודפשט לזוגות ולמשפחות`
- `איפה לישון בפוקט: אזורים, מלונות ומחירים`
- `כמה עולה חופשה בתאילנד לזוג או למשפחה`
- `טיסה ישירה לתאילנד או קונקשן: מחיר, זמן וסיכון`

### Snippet-answer pattern

The first answer block should provide:

1. A direct answer in two or three sentences.
2. The relevant date and source scope.
3. A compact comparison table where useful.
4. A next action that matches the query.

### Commercial-content boundary

- Editorial facts need sources and review dates.
- Volatile prices need a supplier, scope, currency, and verification time.
- Affiliate relationships need disclosure.
- Structured data must match visible content.
- A long guide must earn its length through decisions, tables, sources, original analysis, and useful navigation. Word count alone is not a quality target.
- Each indexable page needs a unique intent. Do not create near-duplicate destination and keyword combinations solely for coverage.

## CTA and conversion measurement

Copy is successful only when it creates a faster, more trusted decision.

Instrument these transitions:

1. Entry intent selected.
2. Search or natural-language request submitted.
3. First valid result shown.
4. Proposal opened.
5. Price revalidation requested.
6. Current offer returned.
7. Checkout or supplier handoff opened.
8. Booking confirmation or supplier postback received.
9. Attachment purchased.
10. Trip cockpit opened after purchase.

For each primary CTA, record:

- visible label
- page and component
- product
- intent
- actual action type
- result state
- time to response
- error category
- supplier when applicable
- revenue outcome when confirmed

Do not optimize click-through rate in isolation. Guard against price mismatch, dead-end handoffs, accidental clicks, repeated submissions, refunds, complaints, and support burden.

## Implementation sequence

### P0: conversion and truth

1. Replace platform-oriented hero, map, plan, and directory copy.
2. Keep product nouns in desktop and mobile navigation.
3. Give every search tab a product-specific submit label.
4. Replace bare `בדיקה חיה` placeholders where they visually resemble prices.
5. Implement the map click acknowledgement, proof, and exact-action pattern.
6. Separate search, revalidation, supplier handoff, checkout, and charge CTAs.
7. Add seller, scope, freshness, and terms near each actionable price.
8. Add truthful timeout, conflict, expired-session, no-result, and price-change messages.

### P1: complete journey

1. Apply the intent system to destination hubs, guides, insurance, hotels, flights, packages, and saved trips.
2. Convert account terminology into traveler language while preserving accessible status details.
3. Connect guide CTAs to prefilled product searches and exact map states.
4. Add localized messages for flexible-date, budget-first, family, kosher, accessibility, and multi-city intents.

### P2: optimization

1. Test hero question and primary CTA variants by intent source.
2. Test three-proposal presentation against a conventional result list.
3. Test full-party total prominence against per-person price prominence, with revenue and complaint guardrails.
4. Test contextual attachments only after the core offer is understood.
5. Expand languages only after Hebrew terminology, mixed-direction rendering, and support ownership are stable.

## Acceptance checklist

### Public copy

- [ ] The first screen names a holiday outcome or travel product.
- [ ] The primary CTA describes the exact next action.
- [ ] There is one visually dominant primary action per decision surface.
- [ ] Internal architecture terms do not appear in customer-facing copy.
- [ ] AI is framed as a way to express intent, not as unsupported magic.
- [ ] No superlative, urgency, availability, live-price, savings, booking, or payment claim appears without evidence.

### Price and offer

- [ ] Every displayed price states its scope.
- [ ] The selected travelers, rooms, dates, currency, and inclusions are visible.
- [ ] Seller, support owner, and verification state are clear before commitment.
- [ ] Affiliate handoff and direct checkout use different CTA language.
- [ ] The payment CTA states whether it charges the traveler.

### Map and motion

- [ ] Every Earth click acknowledges the selected object.
- [ ] The next useful action is available without covering the map.
- [ ] Unknown points do not inherit stale destination or price data.
- [ ] Progress motion corresponds to a real state transition.
- [ ] Success, attention, failure, cancellation, and timeout are visually and verbally distinct.
- [ ] Reduced-motion users receive the same state information without animation.

### Mobile and accessibility

- [ ] Controls have at least a 44 by 44 CSS-pixel target.
- [ ] Button labels remain understandable out of context.
- [ ] Focus moves only when the interface explicitly announces that it moved.
- [ ] Async actions have a bounded waiting state and a recovery action.
- [ ] Hebrew, numbers, currencies, and LTR identifiers render in a comprehensible order.
- [ ] Status announcements are concise and do not repeat decorative text.

### SEO and AEO

- [ ] Each page targets one clear user intent.
- [ ] The title and opening answer use the searcher's language.
- [ ] Volatile facts show source and review time.
- [ ] Structured data matches visible content.
- [ ] Commercial relationships are disclosed.
- [ ] Long-form content contains original decision value rather than repeated filler.

## Source notes

- Israeli competitor public copy: [ISSTA](https://www.issta.co.il/), [Travelist](https://www.travelist.co.il/), [Eshet Tours](https://www.eshet.com/), [Gulliver](https://www.gulliver.co.il/), [Ophir Tours](https://www.ophirtours.co.il/), [Secret Flights](https://secretflights.co.il/).
- Button intent, visual priority, mobile target considerations, loading states, and payment consequence clarity: [Baymard button research](https://baymard.com/learn/button-design).
- The wider product, inventory, conversion, monetization, supplier, SEO, and competitive evidence is maintained in [COMPETITIVE_GAP_ANALYSIS.md](./COMPETITIVE_GAP_ANALYSIS.md).
