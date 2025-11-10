<?php
if (!session_id()) {
    session_start();
}
$milk_dir = __DIR__."/../milkadmin";
$local_dir = __DIR__."/../milkadmin_local";

if (!defined('MILK_DIR')) {
    define('MILK_DIR', realpath($milk_dir));
}
if (!is_dir(MILK_DIR)) {
    ?>
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto;">
    <h2 style="color: red; text-align: center;">The core of Milk Admin was not found: <?php echo $milk_dir; ?></h2>
    <p>Open the public_html/milkadmin.php file and edit the $milk_dir variable and $local_dir variable to point to the correct path of the milkadmin directory.</p>
    </div>
    <?php
    die ();
}
$link_complete = '';
if (isset($_SESSION['link_complete'])) {
    $link_complete = $_SESSION['link_complete'];
} else {
    if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_SCHEME'])) {
        $uri = explode('?', $_SERVER['REQUEST_URI']);
        $link_complete =   $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $uri[0];
        $_SESSION['link_complete'] = $link_complete;
    }  
}
if (!defined('BASE_URL')) {
    define('BASE_URL', $link_complete);
} 
if (!defined('BASE_URL')) {
    define('BASE_URL', $link_complete);
} 
if (!defined('LOCAL_DIR')) {
    define('LOCAL_DIR', realpath($local_dir));
}
if (!is_dir(LOCAL_DIR)) {
     ?>
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto;">
    <h2 style="color: red; text-align: center;">The local directory of Milk Admin was not found: <?php echo $local_dir; ?></h2>
    <p>Open the public_html/milkadmin.php file and edit the $local_dir variable to point to the correct path of the milkadmin_local directory.</p>
    </div>
    <?php
    die ();
}
// se è scrivibile il file milkadmin.php allora lo scrivo
if (is_writable(__DIR__)) {
    $file = "<?php
if (!defined('MILK_DIR')) {
    define('MILK_DIR', realpath('".$milk_dir."'));
}
if (!defined('BASE_URL')) {
    // base_url è scritta anche dentro milkadmin/config.php
    define('BASE_URL', '".$link_complete."');
}
if (!defined('LOCAL_DIR')) {
    define('LOCAL_DIR', realpath('".$local_dir."'));
}
"; 
    file_put_contents(__DIR__.'/milkadmin.php', $file);
    opcache_reset();
} 