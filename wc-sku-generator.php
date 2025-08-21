<?php
/*
Plugin Name: تولید کننده خودکار شناسه SKU
Version: 1.0.0
*/

if (!defined('ABSPATH')) exit;

class bs_BaranSKUGenerator
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'bs_add_plugin_menu']);
        add_action('save_post_product', [$this, 'bs_generate_sku_for_product'], 10, 2);
        add_action('admin_init', [$this, 'bs_register_settings']);
    }

    public function bs_add_plugin_menu()
    {
        add_options_page(
            'تنظیمات افزونه تولید کننده SKU',
            'تنظیمات SKU',
            'manage_options',
            'baran-sku-generator',
            [$this, 'bs_plugin_settings_page']
        );
    }

    public function bs_plugin_settings_page()
    {
        $total_products = $this->bs_get_total_products_count();
        $recommended_digits = ceil(log10($total_products));

        if (isset($_POST['bs_integrate_sku']) && check_admin_referer('bs_integrate_sku_nonce')) {
            $this->bs_generate_sku_for_existing_products();
            echo '<div class="updated"><p>شناسه‌های SKU برای محصولات قدیمی و ورییشن‌ها (متغیرها) با موفقیت بروزرسانی شد.</p></div>';
        }

        if (isset($_POST['bs_delete_all_skus']) && check_admin_referer('bs_delete_all_skus_nonce')) {
            $this->bs_delete_all_skus();
            echo '<div class="updated"><p>تمام شناسه‌های SKU با موفقیت حذف شدند.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>تنظیمات افزونه تولید کننده SKU</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bs_sku_settings');
                do_settings_sections('baran-sku-generator');
                submit_button();
                ?>
            </form>
            <h2 style="color: red;">تعداد کل محصولات و ورییشن‌ها (متغیرها): <?php echo $total_products; ?></h2>
            <p>برای جلوگیری از تکراری شدن شناسه SKU، پیشنهاد می‌شود حداقل از <?php echo $recommended_digits; ?> رقم
                استفاده کنید.</p>

            <h2>یکپارچه‌سازی SKU برای محصولات قدیمی و متغیر</h2>
            <form method="post">
                <?php wp_nonce_field('bs_integrate_sku_nonce'); ?>
                <p>
                    <input type="checkbox" name="confirm_integration" value="1" required/>
                    تایید می‌کنم که می‌خواهم برای تمامی محصولات قدیمی و متغیرها SKU جدید ایجاد شود.
                </p>
                <p>
                    <input type="submit" name="bs_integrate_sku" class="button-primary" value="شروع یکپارچه‌سازی"/>
                </p>
            </form>

            <h2>حذف کامل شناسه SKU</h2>
            <form method="post">
                <?php wp_nonce_field('bs_delete_all_skus_nonce'); ?>
                <p style="color: red;">
                    <strong>اخطار:</strong> با انتخاب این گزینه، تمام شناسه‌های SKU برای محصولات و ورییشن‌ها (متغیرها)
                    از دیتابیس حذف خواهند شد و این عملیات غیر قابل بازگشت است.
                </p>
                <p>
                    <input type="checkbox" name="confirm_delete" value="1" required/>
                    تایید می‌کنم که می‌خواهم تمام شناسه‌های SKU را حذف کنم.
                </p>
                <p>
                    <input type="submit" name="bs_delete_all_skus" class="button-primary" style="background-color: red;"
                           value="حذف کامل SKUها"/>
                </p>
            </form>
        </div>
        <?php
    }

    public function bs_register_settings()
    {
        register_setting('bs_sku_settings', 'bs_sku_prefix');
        register_setting('bs_sku_settings', 'bs_use_id_for_sku');
        register_setting('bs_sku_settings', 'bs_sku_digits');

        add_settings_section('bs_sku_section', 'تنظیمات عمومی', null, 'baran-sku-generator');
        add_settings_field('bs_sku_prefix', 'پیشوند SKU', [$this, 'bs_sku_prefix_callback'], 'baran-sku-generator', 'bs_sku_section');
        add_settings_field('bs_use_id_for_sku', 'استفاده از ID محصول برای ساخت SKU', [$this, 'bs_use_id_for_sku_callback'], 'baran-sku-generator', 'bs_sku_section');
        add_settings_field('bs_sku_digits', 'تعداد ارقام SKU', [$this, 'bs_sku_digits_callback'], 'baran-sku-generator', 'bs_sku_section');
    }

    public function bs_sku_prefix_callback()
    {
        $prefix = get_option('bs_sku_prefix', 'bs');
        echo '<input type="text" name="bs_sku_prefix" value="' . esc_attr($prefix) . '" />';
    }

    public function bs_use_id_for_sku_callback()
    {
        $use_id = get_option('bs_use_id_for_sku', false);
        echo '<input type="checkbox" name="bs_use_id_for_sku" value="1" ' . checked(1, $use_id, false) . ' />';
        echo '<label>در صورت فعال بودن، شناسه SKU فقط بر اساس ID محصول ساخته می‌شود.</label>';
    }

    public function bs_sku_digits_callback()
    {
        $digits = get_option('bs_sku_digits', 8);
        $total_products = $this->bs_get_total_products_count();
        $recommended_digits = ceil(log10($total_products));
        echo '<input type="number" name="bs_sku_digits" value="' . esc_attr($digits) . '" min="1" />';
        echo '<p>پیشنهاد: حداقل تعداد ارقام را ' . $recommended_digits . ' وارد کنید تا از تکراری شدن شناسه‌ها جلوگیری شود.</p>';
    }

    public function bs_generate_sku_for_product($post_id, $post)
    {
        if ($post->post_type !== 'product' || wp_is_post_revision($post_id)) return;

        $use_id_for_sku = get_option('bs_use_id_for_sku', false);
        $prefix = get_option('bs_sku_prefix', 'bs');
        $digits = (int)get_option('bs_sku_digits', 8);

        if ($use_id_for_sku) {
            $sku = $post_id;
        } else {
            $sku = $prefix . $this->bs_generate_random_number($digits);
        }

        $beforeSKU = get_post_meta($post_id, '_sku', true);
        if (empty($beforeSKU)) {
            update_post_meta($post_id, '_sku', $sku);
        }

        $product = wc_get_product($post_id);
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation_sku = $use_id_for_sku ? $variation_id : $prefix . $this->bs_generate_random_number($digits);

                $beforeVariationSKU = get_post_meta($variation_id, '_sku', true);
                if (empty($beforeVariationSKU)) {
                    update_post_meta($variation_id, '_sku', $variation_sku);
                }
            }
        }
    }

    private function bs_generate_sku_for_existing_products()
    {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $products = get_posts($args);
        $use_id_for_sku = get_option('bs_use_id_for_sku', false);
        $prefix = get_option('bs_sku_prefix', 'bs');
        $digits = (int)get_option('bs_sku_digits', 8);

        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            $sku = $use_id_for_sku ? $product_post->ID : $prefix . $this->bs_generate_random_number($digits);

            $beforeSKU = get_post_meta($product->get_id(), '_sku', true);
            if (empty($beforeSKU)) {
                update_post_meta($product->get_id(), '_sku', $sku);
            }

            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    $variation_sku = $use_id_for_sku ? $variation_id : $prefix . $this->bs_generate_random_number($digits);

                    $beforeVariationSKU = get_post_meta($variation_id, '_sku', true);
                    if (empty($beforeVariationSKU)) {
                        update_post_meta($variation_id, '_sku', $variation_sku);
                    }
                }
            }
        }
    }

    private function bs_delete_all_skus()
    {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $products = get_posts($args);

        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            delete_post_meta($product->get_id(), '_sku');

            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    delete_post_meta($variation_id, '_sku');
                }
            }
        }
    }

    private function bs_generate_random_number($digits)
    {
        return str_pad(mt_rand(0, pow(10, $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }

    private function bs_get_total_products_count()
    {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];
        $products = get_posts($args);
        $count = 0;
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            $count++;
            if ($product->is_type('variable')) {
                $count += count($product->get_children());
            }
        }
        return $count;
    }
}

new bs_BaranSKUGenerator();
?>
