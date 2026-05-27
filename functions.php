<?php
/**
 * Tra-Vel Revenue theme functions.
 */

if (!defined('ABSPATH')) {
    exit;
}

function travel_revenue_setup(): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('editor-styles');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_editor_style('style.css');
    register_nav_menus([
        'primary' => __('Primary Menu', 'travel-revenue'),
    ]);
}
add_action('after_setup_theme', 'travel_revenue_setup');

function travel_revenue_assets(): void {
    wp_enqueue_style('travel-revenue-style', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'travel_revenue_assets');

function travel_revenue_lead_statuses(): array {
    return [
        'new' => __('New', 'travel-revenue'),
        'qualified' => __('Qualified', 'travel-revenue'),
        'supplier_research' => __('Supplier research', 'travel-revenue'),
        'offer_needed' => __('Offer needed', 'travel-revenue'),
        'partner_sent' => __('Sent to partner', 'travel-revenue'),
        'booked' => __('Booked', 'travel-revenue'),
        'closed_lost' => __('Closed lost', 'travel-revenue'),
    ];
}

function travel_revenue_register_lead_type(): void {
    register_post_type('travel_lead', [
        'labels' => [
            'name' => __('Travel Leads', 'travel-revenue'),
            'singular_name' => __('Travel Lead', 'travel-revenue'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-airplane',
        'supports' => ['title', 'editor', 'custom-fields'],
    ]);

    $meta_fields = [
        'lead_name',
        'lead_phone',
        'lead_email',
        'destination',
        'trip_type',
        'departure_month',
        'traveler_count',
        'budget_range',
        'services_needed',
        'lead_timeline',
        'lead_status',
        'lead_consent',
        'landing_url',
        'referrer_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    foreach ($meta_fields as $field) {
        register_post_meta('travel_lead', $field, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]);
    }
}
add_action('init', 'travel_revenue_register_lead_type');

function travel_revenue_clean(string $key): string {
    return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
}

function travel_revenue_clean_url(string $key): string {
    return isset($_POST[$key]) ? esc_url_raw(wp_unslash($_POST[$key])) : '';
}

function travel_revenue_handle_lead(): void {
    if (!isset($_POST['travel_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['travel_nonce'])), 'travel_lead')) {
        wp_safe_redirect(add_query_arg('lead', 'bad_nonce', home_url('/')));
        exit;
    }

    if (travel_revenue_clean('company_website') !== '') {
        wp_safe_redirect(add_query_arg('lead', 'received', home_url('/')));
        exit;
    }

    $name = travel_revenue_clean('lead_name');
    $phone = travel_revenue_clean('lead_phone');
    $email = sanitize_email(wp_unslash($_POST['lead_email'] ?? ''));
    $destination = travel_revenue_clean('destination');
    $trip_type = travel_revenue_clean('trip_type');
    $departure_month = travel_revenue_clean('departure_month');
    $traveler_count = travel_revenue_clean('traveler_count');
    $budget_range = travel_revenue_clean('budget_range');
    $services_needed = travel_revenue_clean('services_needed');
    $timeline = travel_revenue_clean('lead_timeline');
    $message = sanitize_textarea_field(wp_unslash($_POST['lead_message'] ?? ''));
    $consent = isset($_POST['lead_consent']) ? 'yes' : '';

    if ($name === '' || $phone === '' || $destination === '' || $consent !== 'yes') {
        wp_safe_redirect(add_query_arg('lead', 'missing_required', home_url('/#lead')));
        exit;
    }

    $title = sprintf('%s - %s - %s', $name, $destination, current_time('Y-m-d H:i'));
    $lead_id = wp_insert_post([
        'post_type' => 'travel_lead',
        'post_status' => 'private',
        'post_title' => $title,
        'post_content' => $message,
    ], true);

    if (!is_wp_error($lead_id)) {
        $fields = [
            'lead_name' => $name,
            'lead_phone' => $phone,
            'lead_email' => $email,
            'destination' => $destination,
            'trip_type' => $trip_type,
            'departure_month' => $departure_month,
            'traveler_count' => $traveler_count,
            'budget_range' => $budget_range,
            'services_needed' => $services_needed,
            'lead_timeline' => $timeline,
            'lead_status' => 'new',
            'lead_consent' => $consent,
            'landing_url' => travel_revenue_clean_url('landing_url') ?: home_url('/'),
            'referrer_url' => travel_revenue_clean_url('referrer_url') ?: esc_url_raw(wp_get_referer() ?: ''),
            'utm_source' => travel_revenue_clean('utm_source'),
            'utm_medium' => travel_revenue_clean('utm_medium'),
            'utm_campaign' => travel_revenue_clean('utm_campaign'),
            'utm_term' => travel_revenue_clean('utm_term'),
            'utm_content' => travel_revenue_clean('utm_content'),
        ];

        foreach ($fields as $key => $value) {
            update_post_meta($lead_id, $key, $value);
        }

        wp_mail(get_option('admin_email'), 'Tra-Vel lead: ' . $title, wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    wp_safe_redirect(add_query_arg('lead', 'received', home_url('/')));
    exit;
}
add_action('admin_post_nopriv_travel_lead', 'travel_revenue_handle_lead');
add_action('admin_post_travel_lead', 'travel_revenue_handle_lead');

function travel_revenue_lead_columns(array $columns): array {
    $new_columns = [];
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if ($key === 'title') {
            $new_columns['lead_phone'] = __('Phone', 'travel-revenue');
            $new_columns['destination'] = __('Destination', 'travel-revenue');
            $new_columns['budget_range'] = __('Budget', 'travel-revenue');
            $new_columns['lead_status'] = __('Status', 'travel-revenue');
        }
    }
    return $new_columns;
}
add_filter('manage_travel_lead_posts_columns', 'travel_revenue_lead_columns');

function travel_revenue_lead_column_content(string $column, int $post_id): void {
    if (in_array($column, ['lead_phone', 'destination', 'budget_range'], true)) {
        echo esc_html((string) get_post_meta($post_id, $column, true));
        return;
    }

    if ($column === 'lead_status') {
        $statuses = travel_revenue_lead_statuses();
        $status = (string) get_post_meta($post_id, 'lead_status', true);
        echo esc_html($statuses[$status] ?? $status);
    }
}
add_action('manage_travel_lead_posts_custom_column', 'travel_revenue_lead_column_content', 10, 2);

function travel_revenue_lead_meta_box(): void {
    add_meta_box('travel_lead_details', __('Lead details', 'travel-revenue'), 'travel_revenue_render_lead_meta_box', 'travel_lead', 'normal', 'high');
}
add_action('add_meta_boxes_travel_lead', 'travel_revenue_lead_meta_box');

function travel_revenue_render_lead_meta_box(WP_Post $post): void {
    wp_nonce_field('travel_lead_admin', 'travel_lead_admin_nonce');
    $statuses = travel_revenue_lead_statuses();
    $current_status = (string) get_post_meta($post->ID, 'lead_status', true);
    $rows = [
        __('Name', 'travel-revenue') => get_post_meta($post->ID, 'lead_name', true),
        __('Phone', 'travel-revenue') => get_post_meta($post->ID, 'lead_phone', true),
        __('Email', 'travel-revenue') => get_post_meta($post->ID, 'lead_email', true),
        __('Destination', 'travel-revenue') => get_post_meta($post->ID, 'destination', true),
        __('Trip type', 'travel-revenue') => get_post_meta($post->ID, 'trip_type', true),
        __('Departure month', 'travel-revenue') => get_post_meta($post->ID, 'departure_month', true),
        __('Travelers', 'travel-revenue') => get_post_meta($post->ID, 'traveler_count', true),
        __('Budget', 'travel-revenue') => get_post_meta($post->ID, 'budget_range', true),
        __('Services', 'travel-revenue') => get_post_meta($post->ID, 'services_needed', true),
        __('Timeline', 'travel-revenue') => get_post_meta($post->ID, 'lead_timeline', true),
        __('Landing URL', 'travel-revenue') => get_post_meta($post->ID, 'landing_url', true),
        __('Referrer URL', 'travel-revenue') => get_post_meta($post->ID, 'referrer_url', true),
        __('UTM source', 'travel-revenue') => get_post_meta($post->ID, 'utm_source', true),
        __('UTM campaign', 'travel-revenue') => get_post_meta($post->ID, 'utm_campaign', true),
    ];
    ?>
    <p>
        <label for="lead_status"><strong><?php esc_html_e('Status', 'travel-revenue'); ?></strong></label>
        <select id="lead_status" name="lead_status">
            <?php foreach ($statuses as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status ?: 'new', $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <table class="widefat striped">
        <tbody>
            <?php foreach ($rows as $label => $value) : ?>
                <tr>
                    <th scope="row"><?php echo esc_html($label); ?></th>
                    <td><?php echo esc_html((string) $value); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function travel_revenue_save_lead_status(int $post_id): void {
    if (!isset($_POST['travel_lead_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['travel_lead_admin_nonce'])), 'travel_lead_admin')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    $status = travel_revenue_clean('lead_status');
    if (array_key_exists($status, travel_revenue_lead_statuses())) {
        update_post_meta($post_id, 'lead_status', $status);
    }
}
add_action('save_post_travel_lead', 'travel_revenue_save_lead_status');

function travel_revenue_disclosure(): string {
    return '<aside class="travel-disclosure">' . esc_html__('גילוי מסחרי: חלק מהעמודים עשויים לכלול קישורי שותפים, הפניות לספקים או הצעות בתשלום. אם תבוצע הזמנה דרך קישור כזה ייתכן שנקבל עמלה. מחירים, זמינות, תנאי ביטול, כבודה, ויזה וביטוח חייבים להיבדק מול הספק לפני רכישה.', 'travel-revenue') . '</aside>';
}
add_shortcode('travel_commercial_disclosure', 'travel_revenue_disclosure');

function travel_revenue_schema(): void {
    if (!is_front_page()) {
        return;
    }
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'TravelAgency',
        'name' => 'Tra-Vel',
        'url' => home_url('/'),
        'areaServed' => 'IL',
        'inLanguage' => 'he-IL',
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}
add_action('wp_head', 'travel_revenue_schema');

function travel_revenue_attribution_script(): void {
    if (!is_front_page()) {
        return;
    }
    ?>
    <script>
    (function () {
        var params = new URLSearchParams(window.location.search);
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function (key) {
            var value = params.get(key) || window.localStorage.getItem('travel_' + key) || '';
            if (params.get(key)) {
                window.localStorage.setItem('travel_' + key, params.get(key));
            }
            var input = document.querySelector('[name="' + key + '"]');
            if (input) {
                input.value = value;
            }
        });
        var landing = document.querySelector('[name="landing_url"]');
        var referrer = document.querySelector('[name="referrer_url"]');
        if (landing) {
            landing.value = window.location.href;
        }
        if (referrer) {
            referrer.value = document.referrer;
        }
    }());
    </script>
    <?php
}
add_action('wp_footer', 'travel_revenue_attribution_script');
