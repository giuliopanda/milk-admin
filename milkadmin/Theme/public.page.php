<?php
namespace Theme;

use App\{Theme, Get};
use Theme\Template;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Public page template - No sidebar, centered max-width content with header logo
 *
 * Customizable variables via Theme::set():
 * - public.header.title (default: 'Milk Admin')
 * - public.header.description (default: '')
 * - public.header.logo-path (default: THEME_URL.'/Assets/logo-big.webp')
 * - public.header.title-color (default: '#333')
 * - public.footer.text (default: '© '.date('Y').' Milk Admin')
 * - public.footer.link (default: 'https://milkadmin.org')
 * - public.footer.link-text (default: 'Milk Admin')
 * - public.theme.bg-class (default: 'public-bg-light-gray')
 *
 * Available background classes in theme.css:
 * Light: public-bg-light-gray, public-bg-soft-white, public-bg-warm-white,
 *        public-bg-cool-blue, public-bg-mint, public-bg-lavender, public-bg-peach
 * Gradient: public-bg-gradient-purple, public-bg-gradient-ocean, public-bg-gradient-sunset,
 *           public-bg-gradient-forest, public-bg-gradient-rose, public-bg-gradient-sky,
 *           public-bg-gradient-fire, public-bg-gradient-emerald
 */

// Get customizable values
$headerTitle = Theme::get('public.header.title', 'Milk Admin');
$headerDescription = Theme::get('public.header.description', '');
$logoPath = Theme::get('public.header.logo-path', THEME_URL.'/Assets/logo-big.webp');
$titleColor = Theme::get('public.header.title-color', '#333'); // NON USATO
$footerText = Theme::get('public.footer.text', '© '.date('Y').' Milk Admin');
$footerLink = Theme::get('public.footer.link', 'https://milkadmin.org');
$footerLinkText = Theme::get('public.footer.link-text', 'Milk Admin');
$bgClass = Theme::get('public.theme.bg-class', 'public-bg-light-gray');

Template::getHead();
?>



<div class="public-page-container <?php echo _r($bgClass); ?>">
    

    <main class="public-main">

        <header class="public-header">
                <div class="public-header-title">
                <img src="<?php echo Get::uriPath($logoPath); ?>" alt="<?php echo _r($headerTitle); ?> logo" class="public-logo">
                <h1><?php echo _r($headerTitle); ?></h1>
                </div>
                <?php if ($headerDescription): ?>
                    <p class="public-header-description"><?php echo _r($headerDescription); ?></p>
                <?php endif; ?>
            
        </header>
        <div class="public-content">
            <?php
            foreach (Theme::for('content') as $content) {
                // qui non si deve fare il sanitize perché non è una variabile è il contenuto del sito!
                echo ($content);
            }
            ?>
        </div>


        <footer class="public-footer">
            <?php if ($footerLink): ?>
                <a href="<?php echo _r($footerLink); ?>" target="_blank"><?php echo _r($footerText); ?></a>
            <?php else: ?>
                <?php echo _r($footerText); ?>
            <?php endif; ?>
        </footer>
    </main>
</div>

<?php
Template::getUtilities();
Template::getCloseTheme();
