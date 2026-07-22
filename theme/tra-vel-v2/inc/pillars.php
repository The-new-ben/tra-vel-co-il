<?php
/**
 * Pillar Earth vertical hubs (theme 1.30.0).
 *
 * A pillar hub is a page whose hero is the 3D Earth covered with one
 * vertical's points (diving sites today; cruises, family and conventions
 * later), above substantial server-rendered Hebrew pillar content that
 * anchors a pillar-and-spoke SEO cluster. Every vertical plugs in as one
 * config entry here: the template, the SEO guard and the meta description
 * all read from this single source, so a new vertical never needs a new
 * template.
 *
 * Truth boundary: point facts (coordinates, depth ranges, seasons) are
 * public knowledge for world-famous sites. No prices, no availability
 * claims, no operator names, no invented numbers.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All pillar vertical configs, keyed by kind (the page slug).
 *
 * @return array<string, array<string, mixed>>
 */
function tra_vel_v2_pillar_configs() {
	return array(
		'diving' => array(
			'eyebrow'          => __( 'חופשות צלילה', 'tra-vel-v2' ),
			'icon'             => 'waves',
			'title'            => __( 'חופשת הצלילה הבאה שלכם', 'tra-vel-v2' ),
			'title_em'         => __( 'מתחילה בנקודה על הכדור.', 'tra-vel-v2' ),
			'intro'            => __( 'חופשת צלילה מתחילה בבחירת האתר הנכון, לא בבחירת מלון. על הכדור שלמעלה מסומנים אתרי צלילה מפורסמים מכל העולם, מהים האדום ועד הפסיפיק, עם רמת קושי, טווח עומק ועונה מקובלת לכל אתר. בחרו נקודה שמסקרנת אתכם, ודאו שהיא מתאימה לרמת ההסמכה שלכם, ומשם בונים סביבה את שאר החופשה: טיסות, לינה, ימי יבשה וביטוח.', 'tra-vel-v2' ),
			'meta_description' => __( 'אתרי צלילה מפורסמים בעולם על גלובוס אחד: רמת קושי, טווח עומק ועונה מקובלת לכל אתר, איך בוחרים יעד צלילה ואיך בונים סביבו חופשה שלמה.', 'tra-vel-v2' ),
			'globe_label'      => __( 'גלובוס תלת ממדי של אתרי צלילה בעולם. גררו לסיבוב, בחרו אתר לפרטים, או השתמשו בחיצי המקלדת.', 'tra-vel-v2' ),
			'board_title'      => __( 'אתרי הצלילה על הכדור', 'tra-vel-v2' ),
			'board_intro'      => __( 'כל אתר מדורג לפי רמת קושי: כוכב אחד לצלילה קלה, שניים לרמה בינונית ושלושה לצוללים מנוסים. טווח העומק והעונה הם ידע כללי לתכנון ראשוני.', 'tra-vel-v2' ),
			'safety_note'      => __( 'חשוב לדעת: צלילה מחייבת הסמכה בתוקף המתאימה לרמת האתר ולתנאיו. פרטי העומק והעונות כאן הם ידע כללי לתכנון, והתנאים בפועל נקבעים לפי מזג האוויר והנחיות צוותי הצלילה במקום.', 'tra-vel-v2' ),
			'planner_destination' => 'diving',
			'cta_title'        => __( 'בחרתם אתר? נתכנן סביבו את החופשה', 'tra-vel-v2' ),
			'cta_copy'         => __( 'ספרו לנו איזה אתר צלילה מדבר אליכם, ונרכיב סביבו את התמונה המלאה: טיסות, לינה קרובה למים, ימי יבשה ושאלות הביטוח שכדאי לברר.', 'tra-vel-v2' ),
			'cta_label'        => __( 'פתחו תכנון חופשת צלילה', 'tra-vel-v2' ),
			'points'           => tra_vel_v2_diving_sites(),
			'sections'         => array(
				array(
					'id'         => 'choose-destination',
					'heading'    => __( 'איך בוחרים יעד צלילה', 'tra-vel-v2' ),
					'paragraphs' => array(
						__( 'שלושה שיקולים מכריעים את רוב ההחלטה: עונה, רמה ותקציב. העונה קודמת לכול. לרוב אתרי הצלילה יש חלון שבו הראות טובה, הים רגוע ובעלי החיים הגדולים מגיעים. אותו אתר בדיוק יכול להיות חוויה נהדרת בנובמבר ומאכזבת ביולי, ולכן בכרטיס של כל אתר בעמוד הזה מופיעה העונה המקובלת. חלק מהאתרים, כמו איי סימילאן בתאילנד, סגורים לצלילה מחוץ לעונה, וכדאי לוודא זאת לפני שקובעים תאריכים.', 'tra-vel-v2' ),
						__( 'הרמה חשובה לא פחות. אתר עם זרמים חזקים או עומק גדול דורש ניסיון והסמכה מתאימה, וצוללים חדשים ייהנו הרבה יותר בשונית רדודה ומוגנת. סמני הכוכבים על הכדור מדרגים כל אתר, משונית קלה ועד צלילה למנוסים, כדי שההשוואה תתחיל מהמקום הנכון.', 'tra-vel-v2' ),
						__( 'התקציב מושפע קודם כול מהטיסה. הים האדום קרוב לישראל וקל להגיע אליו, בעוד שהגעה לפלאו, להוואי או לגלפגוס דורשת קונקשנים ארוכים ולעיתים גם שייט. חשבו על עלות הטיסה ועל זמן ההגעה כחלק מהמחיר האמיתי של החופשה, ולא רק על הצלילות עצמן.', 'tra-vel-v2' ),
					),
				),
				array(
					'id'         => 'world-regions',
					'heading'    => __( 'איפה בעולם צוללים ומתי', 'tra-vel-v2' ),
					'paragraphs' => array(
						__( 'המפה שלמעלה מספרת את הסיפור בגדול. הים האדום הוא הבסיס הקרוב: מים חמים, שוניות עשירות ועונה ארוכה, מדהב וראס מוחמד ועד הסטיל באילת והנמל העתיק בקיסריה, שנותנים גם אימון נוח ובדיקת ציוד לפני נסיעה רחוקה. דרום מזרח אסיה מתאימה במיוחד לחורף הישראלי: תאילנד ומלזיה נהנות מעונת שיא בין נובמבר לאפריל, ובאלי מציעה צלילה טובה דווקא בחודשי הקיץ, כך שכמעט בכל חודש בשנה יש אזור שנמצא בעונה שלו.', 'tra-vel-v2' ),
						__( 'האוקיינוס השקט והאמריקות הם הצד ההרפתקני של המפה. פלאו וגלפגוס נחשבות חופשות שמצדיקות תכנון ארוך, עם זרמים, כרישים ולוגיסטיקה של ספינת מגורים, בעוד שמקסיקו ובליז משלבות בולענים ייחודיים עם חופשה קריבית נגישה יותר. מי שמחפש חוויה שונה לגמרי מוצא אותה דווקא בקור: סילפרה שבאיסלנד מזכירה שצלילה היא לא רק שוניות טרופיות, אלא גם מים צלולים במיוחד בין שני לוחות יבשתיים.', 'tra-vel-v2' ),
					),
				),
				array(
					'id'         => 'what-included',
					'heading'    => __( 'מה כוללת חופשת צלילה', 'tra-vel-v2' ),
					'paragraphs' => array(
						__( 'חופשת צלילה טיפוסית בנויה מימי צלילה, ימי יבשה וזמן התאוששות. יום צלילה כולל בדרך כלל שתיים או שלוש צלילות עם הפסקות פני שטח ביניהן, ולכן הוא תופס את רוב שעות האור. חלק מהאתרים נגישים מהחוף, כמו הרציף בקיסריה או הסטיל באילת, ואחרים דורשים שיט יומי או ספינת מגורים שעוגנת ליד השוניות.', 'tra-vel-v2' ),
						__( 'חשוב לתכנן גם את הסיום: מקובל להשאיר פרק זמן של כיממה ללא צלילה לפני הטיסה חזרה, בהתאם להנחיות ארגון ההסמכה שלכם, ולכן נעים לסגור את החופשה ביום יבשה רגוע. ציוד אפשר להביא מהבית או לשכור ביעד. שכירות חוסכת משקל בטיסה, וציוד אישי מוכר חוסך התאמות, ובכל מקרה מסכה שמתאימה לפנים שלכם שווה את המקום במזוודה.', 'tra-vel-v2' ),
					),
				),
				array(
					'id'         => 'family',
					'heading'    => __( 'באים עם ילדים או עם מי שלא צולל', 'tra-vel-v2' ),
					'paragraphs' => array(
						__( 'חופשת צלילה לא חייבת להיות רק לצוללים. ברוב היעדים המסומנים כאן יש גם שנורקול טוב במים רדודים, כך שילדים ובני זוג שלא צוללים רואים את אותה שונית מלמעלה. אתרים כמו השוניות מול קיירנס או מולוקיני בהוואי מתאימים לשילוב של צוללים ומשנרקלים באותה יציאה.', 'tra-vel-v2' ),
						__( 'אם הילדים מתעניינים בצלילה עצמה, ארגוני ההסמכה מציעים מסלולי התנסות והסמכות צעירות בהתאם לגיל ולתנאים, תמיד לפי שיקול הדעת של צוות מקצועי במקום. משפחה שמתכננת סביב זה מראש, עם לינה קרובה למים ולוח זמנים גמיש, נהנית הרבה יותר.', 'tra-vel-v2' ),
					),
				),
				array(
					'id'         => 'safety-insurance',
					'heading'    => __( 'הסמכה, בטיחות וביטוח', 'tra-vel-v2' ),
					'paragraphs' => array(
						__( 'צלילה מחייבת הסמכה מתאימה לכל אתר. רמת ההסמכה, מספר הצלילות שביומן והניסיון בתנאים דומים קובעים לאן נכון לכם לצלול, ומרכזי צלילה רשאים לבקש הוכחות לכך. אל תתכננו אתר שמעבר לרמה הנוכחית שלכם; אפשר לבנות את החופשה כך שתכלול צלילות הדרכה שמקדמות אתכם רמה.', 'tra-vel-v2' ),
						__( 'בצד הביטוח, צלילה ספורטיבית נחשבת בפוליסות רבות פעילות שדורשת הרחבה ייעודית, ולעיתים יש בה מגבלות עומק. לפני שסוגרים את החופשה, בדקו את הכיסוי: האם הפוליסה כוללת צלילה, לאיזה עומק, ומה נדרש כדי להוסיף הרחבה מתאימה. עמוד ביטוח הנסיעות שלנו מרכז את השאלות שכדאי לברר מול המבטח.', 'tra-vel-v2' ),
					),
				),
			),
			'spokes'           => array(
				array(
					'path'        => '/guides/diving/red-sea/',
					'title'       => __( 'צלילה בים האדום: דהב, ראס מוחמד ואילת', 'tra-vel-v2' ),
					'description' => __( 'העונות, האתרים וההבדלים בין סיני לאילת.', 'tra-vel-v2' ),
				),
				array(
					'path'        => '/guides/diving/southeast-asia/',
					'title'       => __( 'צלילה בדרום מזרח אסיה', 'tra-vel-v2' ),
					'description' => __( 'תאילנד, מלזיה ואינדונזיה: מתי לאן, ואיך משלבים חופשה.', 'tra-vel-v2' ),
				),
				array(
					'path'        => '/guides/diving/liveaboard/',
					'title'       => __( 'ספינת מגורים: למי זה מתאים', 'tra-vel-v2' ),
					'description' => __( 'איך נראה שבוע על ספינה, ומה בודקים לפני שמזמינים.', 'tra-vel-v2' ),
				),
				array(
					'path'        => '/guides/diving/family-snorkeling/',
					'title'       => __( 'שנורקול ומשפחות ביעדי צלילה', 'tra-vel-v2' ),
					'description' => __( 'איך בונים חופשה שמתאימה גם למי שנשאר על פני המים.', 'tra-vel-v2' ),
				),
				array(
					'path'        => '/guides/diving/insurance-cover/',
					'title'       => __( 'הרחבת צלילה בביטוח נסיעות', 'tra-vel-v2' ),
					'description' => __( 'אילו שאלות לברר מול המבטח לפני חופשת צלילה.', 'tra-vel-v2' ),
				),
			),
		),
	);
}

/**
 * The diving points dataset: world-famous dive sites, public-knowledge
 * facts only. Difficulty is 1 (easy) to 3 (experienced divers). Depth is a
 * typical recreational dive-profile range in meters; several sites are far
 * deeper than the recreational profile shown.
 *
 * @return array<int, array<string, mixed>>
 */
function tra_vel_v2_diving_sites() {
	return array(
		array(
			'id'         => 'blue-hole-dahab',
			'name'       => __( 'הבלו הול, דהב', 'tra-vel-v2' ),
			'pin_label'  => __( 'בלו הול', 'tra-vel-v2' ),
			'name_en'    => 'Blue Hole, Dahab',
			'latitude'   => 28.572,
			'longitude'  => 34.537,
			'difficulty' => 3,
			'depth_min'  => 7,
			'depth_max'  => 30,
			'season'     => __( 'מרץ עד מאי וספטמבר עד נובמבר', 'tra-vel-v2' ),
			'summary'    => __( 'בולען מפורסם בסיני. הפרופיל הספורטיבי עובר לאורך הדופן, והקשת העמוקה שמורה לצוללים טכניים בלבד.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'ras-mohammed',
			'name'       => __( 'ראס מוחמד', 'tra-vel-v2' ),
			'pin_label'  => __( 'ראס מוחמד', 'tra-vel-v2' ),
			'name_en'    => 'Ras Mohammed',
			'latitude'   => 27.723,
			'longitude'  => 34.253,
			'difficulty' => 2,
			'depth_min'  => 10,
			'depth_max'  => 30,
			'season'     => __( 'כל השנה, שיא הדגה בקיץ', 'tra-vel-v2' ),
			'summary'    => __( 'שמורה בקצה חצי האי סיני שבה נפגשות שוניות שארק ויולנדה, עם שפע דגה ולהקות גדולות בקיץ.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'ss-thistlegorm',
			'name'       => __( 'תיסטלגורם', 'tra-vel-v2' ),
			'pin_label'  => __( 'תיסטלגורם', 'tra-vel-v2' ),
			'name_en'    => 'SS Thistlegorm',
			'latitude'   => 27.814,
			'longitude'  => 33.92,
			'difficulty' => 3,
			'depth_min'  => 16,
			'depth_max'  => 32,
			'season'     => __( 'כל השנה, נוח במיוחד באביב ובסתיו', 'tra-vel-v2' ),
			'summary'    => __( 'ספינת משא בריטית ממלחמת העולם השנייה ששקעה עם מטענה במפרץ סואץ, מצלילות הצוללות המוכרות בים האדום. הזרמים דורשים ניסיון.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'great-barrier-cairns',
			'name'       => __( 'שונית המחסום מול קיירנס', 'tra-vel-v2' ),
			'pin_label'  => __( 'קיירנס', 'tra-vel-v2' ),
			'name_en'    => 'Great Barrier Reef, Cairns',
			'latitude'   => -16.75,
			'longitude'  => 146.0,
			'difficulty' => 1,
			'depth_min'  => 5,
			'depth_max'  => 25,
			'season'     => __( 'יוני עד נובמבר', 'tra-vel-v2' ),
			'summary'    => __( 'השוניות החיצוניות מול קיירנס באוסטרליה, רדודות ומוגנות ברובן, נוחות גם לצוללים מתחילים ולמשנרקלים.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'sipadan-barracuda-point',
			'name'       => __( 'סיפאדן, נקודת הברקודות', 'tra-vel-v2' ),
			'pin_label'  => __( 'סיפאדן', 'tra-vel-v2' ),
			'name_en'    => 'Sipadan, Barracuda Point',
			'latitude'   => 4.115,
			'longitude'  => 118.629,
			'difficulty' => 3,
			'depth_min'  => 5,
			'depth_max'  => 40,
			'season'     => __( 'אפריל עד אוגוסט', 'tra-vel-v2' ),
			'summary'    => __( 'אי אוקייני במלזיה עם קיר יורד ולהקות ברקודות מסתחררות. הזרמים חזקים והכניסה לאי מוגבלת בהיתרים יומיים.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'richelieu-rock',
			'name'       => __( 'רישלייה רוק', 'tra-vel-v2' ),
			'pin_label'  => __( 'רישלייה', 'tra-vel-v2' ),
			'name_en'    => 'Richelieu Rock',
			'latitude'   => 9.362,
			'longitude'  => 98.023,
			'difficulty' => 2,
			'depth_min'  => 8,
			'depth_max'  => 35,
			'season'     => __( 'נובמבר עד אפריל', 'tra-vel-v2' ),
			'summary'    => __( 'צוק בודד בים האנדמני של תאילנד, עשיר במאקרו ובשוניות רכות, ובעונה נראים בו גם כרישים לווייתניים.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'similan-islands',
			'name'       => __( 'איי סימילאן', 'tra-vel-v2' ),
			'pin_label'  => __( 'סימילאן', 'tra-vel-v2' ),
			'name_en'    => 'Similan Islands',
			'latitude'   => 8.65,
			'longitude'  => 97.645,
			'difficulty' => 2,
			'depth_min'  => 5,
			'depth_max'  => 30,
			'season'     => __( 'נובמבר עד אפריל', 'tra-vel-v2' ),
			'summary'    => __( 'פארק ימי תאילנדי של איים וסלעי גרניט עם מצוקים ושוניות רכות. הפארק סגור לצלילה מחוץ לעונה.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'blue-corner-palau',
			'name'       => __( 'בלו קורנר, פלאו', 'tra-vel-v2' ),
			'pin_label'  => __( 'פלאו', 'tra-vel-v2' ),
			'name_en'    => 'Blue Corner, Palau',
			'latitude'   => 7.135,
			'longitude'  => 134.221,
			'difficulty' => 3,
			'depth_min'  => 8,
			'depth_max'  => 30,
			'season'     => __( 'נובמבר עד מאי', 'tra-vel-v2' ),
			'summary'    => __( 'פינת שונית במיקרונזיה עם זרמים חזקים שמרכזים כרישים ולהקות. צוללים נעזרים שם בוו שונית ייעודי.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'uss-liberty-bali',
			'name'       => __( 'ליברטי, טולמבן', 'tra-vel-v2' ),
			'pin_label'  => __( 'באלי', 'tra-vel-v2' ),
			'name_en'    => 'USS Liberty, Bali',
			'latitude'   => -8.274,
			'longitude'  => 115.593,
			'difficulty' => 1,
			'depth_min'  => 5,
			'depth_max'  => 30,
			'season'     => __( 'אפריל עד נובמבר', 'tra-vel-v2' ),
			'summary'    => __( 'ספינת צבא אמריקאית שנפגעה במלחמת העולם השנייה ושקעה מול חוף טולמבן בבאלי. כניסה מהחוף וצלילה נוחה.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'molokini-hawaii',
			'name'       => __( 'מולוקיני, מאווי', 'tra-vel-v2' ),
			'pin_label'  => __( 'מולוקיני', 'tra-vel-v2' ),
			'name_en'    => 'Molokini, Hawaii',
			'latitude'   => 20.63,
			'longitude'  => -156.496,
			'difficulty' => 1,
			'depth_min'  => 5,
			'depth_max'  => 25,
			'season'     => __( 'כל השנה, הים רגוע בבקרים', 'tra-vel-v2' ),
			'summary'    => __( 'לוע געשי בצורת סהר מול מאווי בהוואי, עם מים צלולים ושונית מוגנת שמתאימה גם למשנרקלים.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'silfra-iceland',
			'name'       => __( 'סילפרה, איסלנד', 'tra-vel-v2' ),
			'pin_label'  => __( 'סילפרה', 'tra-vel-v2' ),
			'name_en'    => 'Silfra, Iceland',
			'latitude'   => 64.256,
			'longitude'  => -21.116,
			'difficulty' => 2,
			'depth_min'  => 7,
			'depth_max'  => 18,
			'season'     => __( 'כל השנה', 'tra-vel-v2' ),
			'summary'    => __( 'סדק בין הלוחות הטקטוניים בפארק טינגווליר, עם ראות יוצאת דופן. המים קרים מאוד ונדרש ניסיון בחליפה יבשה.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'great-blue-hole-belize',
			'name'       => __( 'הבלו הול הגדול, בליז', 'tra-vel-v2' ),
			'pin_label'  => __( 'בליז', 'tra-vel-v2' ),
			'name_en'    => 'Great Blue Hole, Belize',
			'latitude'   => 17.316,
			'longitude'  => -87.535,
			'difficulty' => 3,
			'depth_min'  => 30,
			'depth_max'  => 40,
			'season'     => __( 'אפריל עד יוני', 'tra-vel-v2' ),
			'summary'    => __( 'בולען ימי עגול מול חופי בליז, עם נטיפי אבן בעומק. הצלילה עמוקה וקצרה ומיועדת לצוללים מנוסים.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'cenote-dos-ojos',
			'name'       => __( 'סנוטה דוס אוחוס', 'tra-vel-v2' ),
			'pin_label'  => __( 'דוס אוחוס', 'tra-vel-v2' ),
			'name_en'    => 'Cenote Dos Ojos, Mexico',
			'latitude'   => 20.324,
			'longitude'  => -87.39,
			'difficulty' => 2,
			'depth_min'  => 5,
			'depth_max'  => 10,
			'season'     => __( 'כל השנה', 'tra-vel-v2' ),
			'summary'    => __( 'צמד בולענים מחוברים בריביירה מאיה במקסיקו. צלילת קברנים מודרכת לאורך קו קבוע במים מתוקים וצלולים.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'caesarea-ancient-harbor',
			'name'       => __( 'הנמל העתיק, קיסריה', 'tra-vel-v2' ),
			'pin_label'  => __( 'קיסריה', 'tra-vel-v2' ),
			'name_en'    => 'Caesarea Ancient Harbor',
			'latitude'   => 32.502,
			'longitude'  => 34.889,
			'difficulty' => 1,
			'depth_min'  => 2,
			'depth_max'  => 9,
			'season'     => __( 'אפריל עד נובמבר, לפי מצב הים', 'tra-vel-v2' ),
			'summary'    => __( 'פארק ארכיאולוגי תת ימי בין שרידי הנמל ההרודיאני. מסלול מסומן במים רדודים שמתאים גם לצוללים מתחילים.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'satil-eilat',
			'name'       => __( 'הסטיל, אילת', 'tra-vel-v2' ),
			'pin_label'  => __( 'אילת', 'tra-vel-v2' ),
			'name_en'    => 'Satil Wreck, Eilat',
			'latitude'   => 29.505,
			'longitude'  => 34.92,
			'difficulty' => 2,
			'depth_min'  => 21,
			'depth_max'  => 24,
			'season'     => __( 'כל השנה', 'tra-vel-v2' ),
			'summary'    => __( 'ספינת טילים של חיל הים שהוטבעה מול חוף האלמוגים באילת והפכה לשונית מלאכותית פופולרית ונגישה מהחוף.', 'tra-vel-v2' ),
		),
		array(
			'id'         => 'darwin-galapagos',
			'name'       => __( 'האי דרווין, גלפגוס', 'tra-vel-v2' ),
			'pin_label'  => __( 'גלפגוס', 'tra-vel-v2' ),
			'name_en'    => 'Darwin Island, Galapagos',
			'latitude'   => 1.678,
			'longitude'  => -91.989,
			'difficulty' => 3,
			'depth_min'  => 10,
			'depth_max'  => 30,
			'season'     => __( 'יוני עד נובמבר', 'tra-vel-v2' ),
			'summary'    => __( 'האי הצפוני בארכיפלג גלפגוס, צלילת זרמים מספינת מגורים עם כרישי פטיש ובעונה גם כרישים לווייתניים.', 'tra-vel-v2' ),
		),
	);
}

/**
 * Return one vertical's pillar config, or null when the kind is unknown.
 *
 * @param string $kind Vertical kind (page slug).
 * @return array<string, mixed>|null
 */
function tra_vel_v2_pillar_config( $kind ) {
	$kind    = sanitize_key( (string) $kind );
	$configs = tra_vel_v2_pillar_configs();
	if ( '' === $kind || ! isset( $configs[ $kind ] ) ) {
		return null;
	}
	$config         = $configs[ $kind ];
	$config['kind'] = $kind;
	return $config;
}

/**
 * Resolve the pillar kind for a page from its slug.
 *
 * @param int $post_id Optional post ID.
 * @return string
 */
function tra_vel_v2_pillar_kind_for_page( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_queried_object_id();
	if ( ! $post_id ) {
		return '';
	}
	return sanitize_key( (string) get_post_field( 'post_name', $post_id ) );
}

/**
 * Whether the current pillar page carries a complete, publishable config.
 *
 * Fails closed: an unknown kind, an empty points dataset or missing article
 * sections keeps the page out of the index until the config is real.
 *
 * @param int $post_id Optional post ID.
 * @return bool
 */
function tra_vel_v2_pillar_page_is_publishable( $post_id = 0 ) {
	$config = tra_vel_v2_pillar_config( tra_vel_v2_pillar_kind_for_page( $post_id ) );
	if ( ! $config ) {
		return false;
	}
	$points   = isset( $config['points'] ) && is_array( $config['points'] ) ? $config['points'] : array();
	$sections = isset( $config['sections'] ) && is_array( $config['sections'] ) ? $config['sections'] : array();
	return count( $points ) >= 3 && count( $sections ) >= 2;
}

/**
 * Per-vertical meta description for the pillar template.
 *
 * @param int $post_id Optional post ID.
 * @return string
 */
function tra_vel_v2_pillar_meta_description( $post_id = 0 ) {
	if ( ! tra_vel_v2_pillar_page_is_publishable( $post_id ) ) {
		return '';
	}
	$config = tra_vel_v2_pillar_config( tra_vel_v2_pillar_kind_for_page( $post_id ) );
	return sanitize_text_field( (string) ( $config['meta_description'] ?? '' ) );
}

/**
 * Difficulty label for a 1 to 3 rating.
 *
 * @param int $difficulty Difficulty rating.
 * @return string
 */
function tra_vel_v2_pillar_difficulty_label( $difficulty ) {
	$labels = array(
		1 => __( 'קל', 'tra-vel-v2' ),
		2 => __( 'בינוני', 'tra-vel-v2' ),
		3 => __( 'למנוסים', 'tra-vel-v2' ),
	);
	$difficulty = (int) $difficulty;
	return $labels[ $difficulty ] ?? $labels[2];
}

/**
 * Star glyphs for a 1 to 3 difficulty rating.
 *
 * @param int $difficulty Difficulty rating.
 * @return string
 */
function tra_vel_v2_pillar_difficulty_stars( $difficulty ) {
	return str_repeat( '★', max( 1, min( 3, (int) $difficulty ) ) );
}

/**
 * Published spoke pages for one vertical: only spokes whose WordPress page
 * actually exists and is published are returned, so the hub never renders a
 * dead link.
 *
 * @param array<string, mixed> $config Pillar config.
 * @return array<int, array<string, string>>
 */
function tra_vel_v2_pillar_published_spokes( $config ) {
	$spokes    = isset( $config['spokes'] ) && is_array( $config['spokes'] ) ? $config['spokes'] : array();
	$published = array();
	foreach ( $spokes as $spoke ) {
		$path = isset( $spoke['path'] ) ? trim( (string) $spoke['path'] ) : '';
		if ( '' === $path ) {
			continue;
		}
		$page = get_page_by_path( trim( $path, '/' ) );
		if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
			continue;
		}
		$published[] = array(
			'url'         => get_permalink( $page->ID ),
			'title'       => sanitize_text_field( (string) ( $spoke['title'] ?? get_the_title( $page->ID ) ) ),
			'description' => sanitize_text_field( (string) ( $spoke['description'] ?? '' ) ),
		);
	}
	return $published;
}
