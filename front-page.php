<?php
/**
 * Front page template.
 */
get_header();
?>
<main id="main" class="site-main">
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow">Tra-Vel: חבילות, טיסות, ביטוח ושירותי נסיעה עם כוונת קנייה</p>
                <h1>לפני שסוגרים חופשה באירופה, בודקים יעד, תקציב, ביטוח, טיסות ותנאי ביטול</h1>
                <p class="hero-copy">האתר צריך להיות נכס מסחרי, לא בלוג טיולים כללי. הפוקוס הראשון הוא חופשות עירוניות בבודפשט, פראג ווינה, לצד טיסות זולות, ביטוח נסיעות, eSIM, מלונות ותכנון מסלול שמייצרים לידים והפניות.</p>
                <div class="hero-actions">
                    <a class="button accent" href="#lead">בקשת הצעה לטיול</a>
                    <a class="button secondary" href="#money">לעמודי הכסף</a>
                </div>
                <div class="proof-row" aria-label="בדיקות לפני הזמנה">
                    <div class="proof-item"><strong>מחיר וזמינות</strong><span>טיסות, מלונות וחבילות משתנים מהר; כל מחיר חייב להיבדק מול הספק.</span></div>
                    <div class="proof-item"><strong>ביטוח ותנאי ביטול</strong><span>ביטוח, כבודה, שינוי טיסה, דמי ביטול ומדיניות מלון לפני תשלום.</span></div>
                    <div class="proof-item"><strong>ליד עם כוונה</strong><span>יעד, חודש, מספר נוסעים, תקציב ושירותים נלווים כדי לנתב לספק רלוונטי.</span></div>
                </div>
            </div>
            <aside id="lead" class="lead-card" aria-label="טופס ליד נסיעות">
                <h2>לאן רוצים לטוס?</h2>
                <p>הפנייה נשמרת ב-CRM ומנותבת לפי יעד, תאריכים, תקציב ושירותים כמו ביטוח, eSIM, מלון או חבילה.</p>
                <?php if (isset($_GET['lead']) && $_GET['lead'] === 'received') : ?>
                    <p class="notice success">הפנייה התקבלה. נחזור עם כיוון רלוונטי או ספק מתאים.</p>
                <?php endif; ?>
                <?php if (isset($_GET['lead']) && $_GET['lead'] === 'missing_required') : ?>
                    <p class="notice error">שם, טלפון, יעד ואישור יצירת קשר הם שדות חובה.</p>
                <?php endif; ?>
                <form class="form-grid" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="travel_lead">
                    <?php wp_nonce_field('travel_lead', 'travel_nonce'); ?>
                    <input type="hidden" name="landing_url" value="">
                    <input type="hidden" name="referrer_url" value="">
                    <input type="hidden" name="utm_source" value="">
                    <input type="hidden" name="utm_medium" value="">
                    <input type="hidden" name="utm_campaign" value="">
                    <input type="hidden" name="utm_term" value="">
                    <input type="hidden" name="utm_content" value="">
                    <div class="field hp-field" aria-hidden="true"><label for="company_website">אתר חברה</label><input id="company_website" name="company_website" tabindex="-1" autocomplete="off"></div>
                    <div class="field"><label for="lead_name">שם מלא</label><input id="lead_name" name="lead_name" autocomplete="name" required></div>
                    <div class="field"><label for="lead_phone">טלפון</label><input id="lead_phone" name="lead_phone" autocomplete="tel" required></div>
                    <div class="field"><label for="lead_email">אימייל</label><input id="lead_email" name="lead_email" type="email" autocomplete="email"></div>
                    <div class="field"><label for="destination">יעד</label><select id="destination" name="destination" required><option value="">בחירת יעד</option><option>בודפשט</option><option>פראג</option><option>וינה</option><option>בודפשט + פראג + וינה</option><option>אירופה כללי</option><option>יעד אחר</option></select></div>
                    <div class="field"><label for="trip_type">סוג טיול</label><select id="trip_type" name="trip_type"><option>חופשה זוגית</option><option>משפחה</option><option>חברים</option><option>טיול מאורגן</option><option>עסקים</option></select></div>
                    <div class="field"><label for="departure_month">חודש יציאה</label><input id="departure_month" name="departure_month" placeholder="לדוגמה: יולי / סוכות / גמיש"></div>
                    <div class="field"><label for="traveler_count">מספר נוסעים</label><input id="traveler_count" name="traveler_count" placeholder="לדוגמה: 2 מבוגרים + 2 ילדים"></div>
                    <div class="field"><label for="budget_range">תקציב משוער</label><select id="budget_range" name="budget_range"><option>עד 2,500 ש״ח לאדם</option><option>2,500-5,000 ש״ח לאדם</option><option>5,000-10,000 ש״ח לאדם</option><option>10,000 ש״ח ומעלה</option><option>לא יודע/ת</option></select></div>
                    <div class="field"><label for="services_needed">מה צריך?</label><select id="services_needed" name="services_needed"><option>חבילה מלאה</option><option>טיסות</option><option>מלון</option><option>ביטוח נסיעות</option><option>eSIM / תקשורת</option><option>מסלול ואטרקציות</option></select></div>
                    <div class="field"><label for="lead_timeline">דחיפות</label><select id="lead_timeline" name="lead_timeline"><option>מיידי</option><option>השבוע</option><option>החודש</option><option>רק בודק/ת</option></select></div>
                    <div class="field"><label for="lead_message">העדפות מיוחדות</label><textarea id="lead_message" name="lead_message" placeholder="מלון מרכזי, כבודה, כשרות, ילדים, רכב, ביטוח, תקציב קשיח..."></textarea></div>
                    <label class="consent-field"><input type="checkbox" name="lead_consent" value="yes" required> אני מאשר/ת שמירת פרטי הפנייה ויצירת קשר לצורך בדיקת הצעה, ספק או שירות נסיעה.</label>
                    <button class="button accent" type="submit">שליחת בקשה</button>
                    <p class="notice">אין התחייבות למחיר, זמינות, ויזה, כבודה, תנאי ביטול או התאמת ביטוח. יש לאמת הכל מול הספק לפני רכישה.</p>
                </form>
            </aside>
        </div>
    </section>

    <section id="money" class="section money-band">
        <div class="container">
            <div class="section-head">
                <h2>יעדים שמביאים כסף</h2>
                <p>חופשות עירוניות באירופה הן התחלה טובה: חיפוש מסחרי, טיסות רבות, מלונות, ביטוח, eSIM, אטרקציות ואפשרות למסלול רב-עירוני.</p>
            </div>
            <div class="cards">
                <article class="card"><h3>חופשה בבודפשט</h3><p>טיסות, מלונות, מרחצאות, אזורים לינה וחבילות קצרות.</p><a href="/budapest-vacation/">לטיוטת העמוד</a></article>
                <article class="card"><h3>חופשה בפראג</h3><p>חבילות עירוניות, מלונות מרכזיים, אטרקציות, זוגות ומשפחות.</p><a href="/prague-vacation/">לטיוטת העמוד</a></article>
                <article class="card"><h3>בודפשט פראג וינה</h3><p>מסלול רב-עירוני עם טיסות, רכבות, מלונות ותכנון סדר נכון.</p><a href="/budapest-prague-vienna-trip/">לטיוטת העמוד</a></article>
            </div>
        </div>
    </section>

    <section id="services" class="section">
        <div class="container">
            <div class="section-head">
                <h2>שירותים שמגדילים הכנסה להזמנה</h2>
                <p>כל יעד צריך לחבר את המשתמש לשירותים משלימים: טיסות, ביטוח, eSIM, מלונות, רכב ואטרקציות.</p>
            </div>
            <div class="cards">
                <article class="card"><h3>טיסות זולות לאירופה</h3><p>גמישות תאריכים, נמלי תעופה, כבודה, קונקשן ותנאי שינוי.</p><a href="/cheap-flights-europe/">למבנה העמוד</a></article>
                <article class="card"><h3>ביטוח נסיעות לאירופה</h3><p>כיסוי רפואי, ביטול/קיצור נסיעה, ספורט, כבודה והחרגות.</p><a href="/travel-insurance-europe/">למבנה העמוד</a></article>
                <article class="card"><h3>מלונות, eSIM ואטרקציות</h3><p>שירותים משלימים שניתן לחבר לכל מדריך יעד או הצעה.</p><a href="#lead">לבניית חבילה</a></article>
            </div>
            <?php echo do_shortcode('[travel_commercial_disclosure]'); ?>
        </div>
    </section>
</main>
<?php
get_footer();
