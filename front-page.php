<?php
/**
 * Front page template.
 */
get_header();

$theme_uri = get_theme_file_uri();
$today = wp_date('d.m.Y');

$city_cards = [
    [
        'title' => 'חופשה בבודפשט',
        'tagline' => 'מרחצאות, נהר ולילות ארוכים',
        'copy' => 'בודפשט מתאימה למי שרוצה חופשה עירונית במחיר נוח יחסית, עם מלונות במרכז, שווקים, מרחצאות, בתי קפה וטיולים קצרים לאורך הדנובה.',
        'url' => home_url('/budapest-vacation/'),
        'image' => $theme_uri . '/assets/img/city-budapest.webp',
        'alt' => 'בניין הפרלמנט בבודפשט ליד נהר הדנובה בשעת שקיעה',
    ],
    [
        'title' => 'חופשה בפראג',
        'tagline' => 'גשרים, בירה ומרכז עתיק',
        'copy' => 'פראג טובה לזוגות, משפחות וקבוצות חברים שמחפשים עיר יפה, נוחה להליכה, עם טיסות ישירות, מלונות במגוון רמות ואטרקציות קרובות.',
        'url' => home_url('/prague-vacation/'),
        'image' => $theme_uri . '/assets/img/city-prague.webp',
        'alt' => 'גשר קארל בפראג בשעת זריחה',
    ],
    [
        'title' => 'חופשה בוינה',
        'tagline' => 'ארמונות, מוזיקה וקפה טוב',
        'copy' => 'וינה מתאימה לחופשה אלגנטית ומסודרת, עם תחבורה נוחה, מוזיאונים, ארמונות, אוכל טוב ואפשרות לשלב יום טיול לברטיסלבה או עמק הווכאו.',
        'url' => home_url('/vienna-vacation/'),
        'image' => $theme_uri . '/assets/img/city-vienna.webp',
        'alt' => 'גני ארמון שנברון בוינה',
    ],
];

$faq_items = [
    [
        'question' => 'איך בוחרים יעד ראשון לחופשה קצרה באירופה?',
        'answer' => 'בודפשט בדרך כלל מתאימה למחיר נוח וחופשה קלילה, פראג מתאימה לאווירה רומנטית ומרכז עתיק, ווינה מתאימה למי שמעדיף עיר אלגנטית, נקייה ומסודרת. אם יש לכם ארבעה עד שבעה לילות, אפשר לבדוק גם מסלול משולב.',
    ],
    [
        'question' => 'כמה ימים מומלץ להקדיש לבודפשט, פראג או וינה?',
        'answer' => 'לחופשה עירונית ראשונה כדאי לתכנן שלושה עד ארבעה לילות בכל עיר. במסלול של בודפשט, פראג ווינה כדאי להקדיש לפחות שבעה לילות כדי לא להפוך את הטיול למעבר בין תחנות בלבד.',
    ],
    [
        'question' => 'מתי כדאי להזמין טיסות זולות לאירופה?',
        'answer' => 'כדאי לבדוק כמה חלונות תאריך, לצאת באמצע שבוע אם אפשר, להשוות שדות תעופה קרובים ולבדוק היטב כבודה ותנאי שינוי. מחיר זול לא תמיד משתלם אם שעות הטיסה קשות או אם צריך להוסיף מזוודה יקרה.',
    ],
    [
        'question' => 'האם צריך ביטוח נסיעות לאירופה?',
        'answer' => 'כן. ביטוח נסיעות חשוב גם לחופשה קצרה, במיוחד בגלל כיסוי רפואי, כבודה, ביטול או קיצור נסיעה ופעילויות מיוחדות. לפני רכישה חשוב לבדוק חריגים, מצב רפואי קודם ותקרות כיסוי.',
    ],
];
?>
<main id="main" class="site-main home-page">
    <section class="hero" aria-labelledby="hero-title">
        <picture class="hero-media" aria-hidden="true">
            <source media="(max-width: 760px)" srcset="<?php echo esc_url($theme_uri . '/assets/img/hero-budapest-900.webp'); ?>" type="image/webp">
            <img src="<?php echo esc_url($theme_uri . '/assets/img/hero-budapest-1600.webp'); ?>" alt="" width="1600" height="900" fetchpriority="high" loading="eager" decoding="async">
        </picture>
        <div class="hero-shade" aria-hidden="true"></div>
        <div class="container hero-content">
            <div class="hero-text">
                <p class="eyebrow">בודפשט, פראג, וינה וטיסות לאירופה</p>
                <h1 id="hero-title">חופשות באירופה שמתאימות בדיוק לכם</h1>
                <p class="hero-copy">בודפשט, פראג ווינה, טיסות זולות וביטוח נסיעות במקום אחד. ספרו לנו מתי אתם רוצים לטוס, כמה נוסעים ומה חשוב לכם, ונחזור עם כיוון ברור לחופשה שמתאימה לתקציב ולסגנון שלכם.</p>
                <div class="hero-actions">
                    <a class="button accent" href="#inquiry">קבלו הצעה לחופשה</a>
                    <a class="button secondary" href="<?php echo esc_url(home_url('/budapest-prague-vienna-trip/')); ?>">מסלול בודפשט פראג ווינה</a>
                </div>
            </div>
        </div>
    </section>

    <section id="inquiry" class="search-panel-section" aria-labelledby="inquiry-title">
        <div class="container search-panel">
            <div class="panel-intro">
                <span class="panel-kicker">בדיקת חופשה</span>
                <h2 id="inquiry-title">לאן תרצו לטוס?</h2>
                <p>השאירו פרטים קצרים ונחזור אליכם עם אפשרות מתאימה. אין התחייבות, ואין צורך לדעת מראש את כל הפרטים.</p>
            </div>
            <form class="inquiry-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
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
                <div class="field"><label for="destination">יעד מבוקש</label><select id="destination" name="destination" required><option value="">בחרו יעד</option><option>בודפשט</option><option>פראג</option><option>וינה</option><option>בודפשט + פראג + וינה</option><option>אירופה כללי</option><option>יעד אחר</option></select></div>
                <div class="field"><label for="trip_type">סגנון חופשה</label><select id="trip_type" name="trip_type"><option>חופשה זוגית</option><option>משפחה</option><option>חברים</option><option>טיול מאורגן</option><option>נסיעת עבודה</option></select></div>
                <div class="field"><label for="departure_month">מתי רוצים לצאת?</label><input id="departure_month" name="departure_month" placeholder="לדוגמה: יולי, סוכות, תאריכים גמישים"></div>
                <div class="field"><label for="traveler_count">מספר נוסעים</label><input id="traveler_count" name="traveler_count" placeholder="לדוגמה: 2 מבוגרים + 2 ילדים"></div>
                <div class="field"><label for="budget_range">תקציב משוער</label><select id="budget_range" name="budget_range"><option>עד 2,500 ש"ח לאדם</option><option>2,500 עד 5,000 ש"ח לאדם</option><option>5,000 עד 10,000 ש"ח לאדם</option><option>10,000 ש"ח ומעלה</option><option>עדיין לא החלטנו</option></select></div>
                <div class="field"><label for="services_needed">מה תרצו לבדוק?</label><select id="services_needed" name="services_needed"><option>חבילה מלאה</option><option>טיסות</option><option>מלון</option><option>ביטוח נסיעות</option><option>eSIM ותקשורת</option><option>מסלול ואטרקציות</option></select></div>
                <div class="field"><label for="lead_timeline">מתי תרצו תשובה?</label><select id="lead_timeline" name="lead_timeline"><option>כמה שיותר מהר</option><option>השבוע</option><option>החודש</option><option>רק בודקים אפשרויות</option></select></div>
                <div class="field field-wide"><label for="lead_message">העדפות מיוחדות</label><textarea id="lead_message" name="lead_message" placeholder="מלון מרכזי, כבודה, כשרות, ילדים, רכב, ביטוח, תקציב קשיח או כל פרט שיעזור לדייק את החופשה"></textarea></div>
                <label class="consent-field field-wide"><input type="checkbox" name="lead_consent" value="yes" required> אני מאשר/ת יצירת קשר לצורך בדיקת אפשרויות נסיעה ומבין/ה שהמחירים והזמינות ייבדקו לפני רכישה.</label>
                <button class="button accent field-wide" type="submit">שלחו ונחזור אליכם</button>
                <?php if (isset($_GET['lead']) && $_GET['lead'] === 'received') : ?>
                    <p class="notice success field-wide">הבקשה התקבלה. נחזור אליכם עם כיוון מתאים.</p>
                <?php elseif (isset($_GET['lead']) && $_GET['lead'] === 'missing_required') : ?>
                    <p class="notice error field-wide">שם, טלפון, יעד ואישור יצירת קשר הם שדות חובה.</p>
                <?php else : ?>
                    <p class="notice field-wide">אנחנו לא שולחים ספאם. המטרה היא לחזור אליכם עם אפשרות אחת או יותר שמתאימות למה שביקשתם.</p>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <section id="destinations" class="section" aria-labelledby="destinations-title">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">יעדים חזקים לחופשה עירונית</p>
                <h2 id="destinations-title">שלוש ערים, אופי אחר לכל אחת</h2>
                <p>כל יעד מקבל עמוד עומק משלו כדי שלא לערבב בין כוונות חיפוש שונות: חופשה בבודפשט, חופשה בפראג וחופשה בוינה.</p>
            </div>
            <div class="destination-grid">
                <?php foreach ($city_cards as $card) : ?>
                    <article class="destination-card">
                        <a href="<?php echo esc_url($card['url']); ?>">
                            <img src="<?php echo esc_url($card['image']); ?>" alt="<?php echo esc_attr($card['alt']); ?>" width="900" height="900" loading="lazy" decoding="async">
                            <span class="card-body">
                                <span class="card-kicker"><?php echo esc_html($card['tagline']); ?></span>
                                <strong><?php echo esc_html($card['title']); ?></strong>
                                <span><?php echo esc_html($card['copy']); ?></span>
                                <span class="text-link">כל הפרטים על החופשה</span>
                            </span>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="central-europe" class="section feature-band" aria-labelledby="central-title">
        <div class="container feature-grid">
            <div>
                <p class="eyebrow">מסלול קלאסי במרכז אירופה</p>
                <h2 id="central-title">בודפשט, פראג ווינה בטיול אחד</h2>
                <p>למי שרוצה לראות יותר מעיר אחת, המסלול המשולב מחבר בין שלוש בירות קרובות יחסית עם רכבות, טיסות או שילוב ביניהן. התכנון הנכון מתחיל בסדר הערים, מספר הלילות, זמן המעברים ומלונות שנמצאים ליד תחבורה נוחה.</p>
                <ul class="check-list">
                    <li>מתאים לטיול של שבעה עד עשרה לילות.</li>
                    <li>כדאי לבדוק מראש כבודה, שעות רכבת ומרחק מהמלון לתחנה.</li>
                    <li>העמוד המרכזי מרכז את השיקולים לפני בניית מסלול.</li>
                </ul>
                <a class="button accent" href="<?php echo esc_url(home_url('/budapest-prague-vienna-trip/')); ?>">קראו על המסלול המשולב</a>
            </div>
            <div class="route-card" aria-label="שלבי מסלול אפשריים">
                <span>בודפשט</span>
                <span>פראג</span>
                <span>וינה</span>
            </div>
        </div>
    </section>

    <section id="services" class="section services-section" aria-labelledby="services-title">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">מה בודקים לפני שסוגרים</p>
                <h2 id="services-title">טיסות, ביטוח ותנאים קטנים שעושים הבדל גדול</h2>
                <p>מחיר טוב הוא רק חלק מהחופשה. חשוב לבדוק שעות טיסה, כבודה, תנאי שינוי, מיקום מלון וכיסוי ביטוחי לפני שמחליטים.</p>
            </div>
            <div class="service-grid">
                <article class="service-card">
                    <span class="service-icon" aria-hidden="true">✈</span>
                    <h3>טיסות זולות לאירופה</h3>
                    <p>העמוד עוזר להבין איך להשוות טיסות בצורה נכונה: גמישות בתאריכים, שדות תעופה, קונקשן, כבודה ועלות כוללת.</p>
                    <a class="text-link" href="<?php echo esc_url(home_url('/cheap-flights-europe/')); ?>">מדריך טיסות זולות לאירופה</a>
                </article>
                <article class="service-card">
                    <span class="service-icon" aria-hidden="true">✓</span>
                    <h3>ביטוח נסיעות לאירופה</h3>
                    <p>כיסוי רפואי, ביטול או קיצור נסיעה, כבודה ופעילויות מיוחדות צריכים להתאים לסוג החופשה ולמצב האישי.</p>
                    <a class="text-link" href="<?php echo esc_url(home_url('/travel-insurance-europe/')); ?>">מה חשוב לבדוק בביטוח</a>
                </article>
                <article class="service-card">
                    <span class="service-icon" aria-hidden="true">₪</span>
                    <h3>תקציב אמיתי לחופשה</h3>
                    <p>טיסה זולה יכולה להתייקר עם מזוודות, העברות ומלון רחוק. אנחנו מסתכלים על העלות הכוללת, לא רק על המחיר הראשון שמופיע.</p>
                    <a class="text-link" href="#inquiry">בדקו אפשרויות לפי תקציב</a>
                </article>
            </div>
        </div>
    </section>

    <section id="why-us" class="section trust-section" aria-labelledby="why-title">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">למה לתכנן איתנו</p>
                <h2 id="why-title">פחות רעש, יותר החלטה ברורה</h2>
            </div>
            <div class="trust-grid">
                <div><strong>שיחה בעברית</strong><span>מסבירים בפשטות מה כדאי לבדוק לפני שמזמינים.</span></div>
                <div><strong>התאמה אישית</strong><span>החופשה נבנית לפי יעד, תאריכים, תקציב והרכב הנוסעים.</span></div>
                <div><strong>בלי מחירים מומצאים</strong><span>לא מציגים מחיר לפני שבודקים זמינות ותנאים.</span></div>
                <div><strong>בדיקה רחבה</strong><span>טיסות, מלונות, ביטוח ושירותים משלימים נבדקים יחד.</span></div>
            </div>
        </div>
    </section>

    <section id="faq" class="section faq-section" aria-labelledby="faq-title">
        <div class="container narrow">
            <div class="section-head solo">
                <p class="eyebrow">שאלות נפוצות</p>
                <h2 id="faq-title">מה כדאי לדעת לפני חופשה באירופה?</h2>
            </div>
            <div class="faq-list">
                <?php foreach ($faq_items as $item) : ?>
                    <details>
                        <summary><?php echo esc_html($item['question']); ?></summary>
                        <p><?php echo esc_html($item['answer']); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
            <?php echo do_shortcode('[travel_commercial_disclosure]'); ?>
        </div>
    </section>

    <section class="final-cta" aria-labelledby="final-title">
        <div class="container final-cta-inner">
            <div>
                <p class="eyebrow">עודכן לאחרונה: <?php echo esc_html($today); ?></p>
                <h2 id="final-title">כתבו לנו לאן בא לכם, ונחזור עם כיוון לחופשה</h2>
            </div>
            <a class="button accent" href="#inquiry">בדיקת חופשה עכשיו</a>
        </div>
    </section>
</main>
<?php
get_footer();
