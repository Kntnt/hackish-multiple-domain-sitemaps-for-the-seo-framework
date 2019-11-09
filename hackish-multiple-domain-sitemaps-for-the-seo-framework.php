<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Hackish Multiple Domain Sitemaps for The SEO Framework
 * Plugin URI:        https://www.kntnt.com/
 * Description:       Makes The SEO Framework to generate different sitemaps for different domain names used for a single site. Please notice that you must edit two variables in the constructor.
 * Version:           1.0.0
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

defined( 'ABSPATH' ) && new Plugin;

class Plugin {

    private $map;

    public function __construct() {

        /*
         * TODO: EDIT THE DOMAINS AND ROOTS
         * The keys are the domains used for this site.
         * The values are each domain's "root" relative the site with no leading
         * slash. Multiple values are separated by |.
         */
        $this->map = [
            'distributors.bayneurope.com' => 'for-partners|en/for-partners',
            'investors.bayneurope.com' => 'investors|en/investors',
            'media.bayneurope.com' => 'nyheter-press|en/news-press',
        ];

        /*
         * TODO: EDIT THE LOCALES
         * The values are the locales that sitemaps are created for.
         */
        $locales = [
            'sv_se',
            'en_us',
        ];

        // See \The_SEO_Framework\Cache::get_sitemap_transient_name()
        $transient_name = 'tsf_sitemap';
        $sitemap_revision = '5';
        $site_id = get_current_blog_id();

        foreach ( $locales as $locale ) {
            $transient = "transient_{$transient_name}_{$sitemap_revision}_{$site_id}_{$locale}";
            add_filter( $transient, function ( $value ) use ( $transient ) { return $this->sitemap( $value, $transient ); }, 10 );
        }

        add_filter( 'the_seo_framework_get_options', [ $this, 'get_options' ], 10, 2 );

    }

    public function get_options( $settings, $setting ) {
        // This hackish plugin uses hooks in the transient API to alter
        // sitemaps. Thus, the sitemap generation mist use  the transient API.
        $settings['cache_sitemap'] = true;
        return $settings;
    }

    public function sitemap( $value, $transient ) {

        if ( ! $value ) {
            $value = $this->generate( $transient );
        }

        foreach ( $this->map as $domain => $root ) {
            if ( $domain == $_SERVER['HTTP_HOST'] ) {
                return $this->filter( $value, $domain, $root );
            }
        }

        return $value;
    }

    private function filter( $value, $domain, $root ) {
        $root = str_replace( '/', '\/', $root );
        $value = preg_replace_callback( "/<url>\s*<loc>https?:\/\/$domain\/(.*?)<\/loc>\s*(<lastmod>.*?<\/lastmod>).*?<\/url>/s", function ( $matches ) use ( $root ) {
            if ( preg_match( "/^(?:$root)(|\/.*)$/m", $matches[1] ) ) {
                return "<url><loc>https://{$_SERVER['HTTP_HOST']}/{$matches[1]}</loc>{$matches[2]}</url>";
            }
            else {
                return '';
            }
        }, $value );
        return trim( preg_replace( '/\s/s', '', $value ) );
    }

    /*
     * Based on /wp-content/plugins/autodescription/inc/views/sitemap/xml-sitemap.php
     */
    private function generate( $transient ) {

        $sitemap_base = new \The_SEO_Framework\Builders\Sitemap_Base;
        $sitemap_base->prepare_generation();

        $value = $sitemap_base->build_sitemap();

        $sitemap_base->shutdown_generation();
        $sitemap_base = null;

        set_transient( $transient, $value, WEEK_IN_SECONDS );

        return $value;

    }

}
