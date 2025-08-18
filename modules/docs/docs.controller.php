<?php
namespace Modules\docs;
use MilkCore\Hooks; 
use MilkCore\Theme;
use MilkCore\Route;
use MilkCore\Get;
use MilkCore\Permissions;

!defined('MILK_DIR') && die(); // Avoid direct access

// Classe per gestire i metadati dei documenti
class DocMetadata {
    public $title = '';
    public $category = 'Uncategorized';
    public $order = 100;
    public $tags = [];
    
    public function __construct($file_path) {
        $this->parse_metadata($file_path);
    }


    function get_first_lines($file_path, $num_righe = 20) {
        $righe = [];
        $handle = fopen($file_path, 'r');
        
        if ($handle) {
            for ($i = 0; $i < $num_righe && !feof($handle); $i++) {
                $riga = fgets($handle);
                if ($riga !== false) {
                    $righe[] = rtrim($riga, "\r\n");
                }
            }
            fclose($handle);
        }
        
        return implode("\n", $righe);
    }
    
    private function parse_metadata($file_path) {
        if (!file_exists($file_path)) return;
        
        $content = $this->get_first_lines($file_path);

        if (preg_match('/@title\s+(.+)$/m', $content, $m)) {
            $this->title = trim($m[1]);
        }
        
        if (preg_match('/@category\s+(.+)$/m', $content, $m)) {
            $this->category = trim($m[1]);
        }
        if (preg_match('/@order\s+(\d+)\s*$/m', $content, $m)) {  
            $this->order = (int)trim($m[1]);
        }
        
        if (preg_match('/@tags\s+(.+)$/m', $content, $m)) {
            $this->tags = array_map('trim', explode(',', $m[1]));
        }

        
        // Se non c'è un titolo, usa il nome del file
        if (empty($this->title)) {
            $this->title = ucfirst(str_replace(["-", ".page.php"], [" ", ""], basename($file_path)));
        }
    }
}

// Funzione per scansionare e organizzare i documenti
function scanDocumentationFiles() {
    $docs_path = MILK_DIR . '/modules/docs/pages';
    $documents = [];
    
    // Funzione ricorsiva per scansionare le directory
    $scanDir = function($dir, $base_path = '') use (&$scanDir, &$documents) {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $full_path = $dir . '/' . $file;
            $relative_path = $base_path ? $base_path . '/' . $file : $file;
            
            if (is_dir($full_path)) {
                $scanDir($full_path, $relative_path);
            } elseif (is_file($full_path) && strpos($file, '.page.php') !== false) {
                $metadata = new DocMetadata($full_path);
                $documents[] = [
                    'path' => '/modules/docs/pages/' . $relative_path,
                    'file' => $file,
                    'title' => $metadata->title,
                    'category' => $metadata->category,
                    'order' => $metadata->order,
                    'tags' => $metadata->tags,
                    'search_text' => strtolower($metadata->title . ' ' . implode(' ', $metadata->tags))
                ];
            }
        }
        
    };
    
    $scanDir($docs_path);
    
    // Ordina i documenti per categoria e ordine
    usort($documents, function($a, $b) {
        if ($a['category'] == $b['category']) {
            return $a['order'] - $b['order'];
        }
        return strcmp($a['category'], $b['category']);
    });
    return $documents;
}

// Funzione per caricare la pagina con la sidebar personalizzata
function load_docs_page_with_sidebar($content = null) {
    // Prima, carica il contenuto della pagina
    if (is_string($content) && is_file($content)) {
        ob_start();
        require $content;
        $contentHtml = ob_get_clean();

        $metadata = new DocMetadata($content);
        
        // Genera la sidebar dei docs
        $docs_sidebar = generatedocs_sidebar();
        
        // Combina la sidebar e il contenuto
        $combinedContent = '
        <div class="container-fluid px-0">
            <div class="row">
                <div class="col-md-3">
                    ' . $docs_sidebar . '
                </div>
                <div class="col-md-9">
                    ' . $contentHtml . '
                </div>
            </div>
        </div>';
        
        // Imposta il contenuto combinato
        Theme::set('content', $combinedContent);

        // Imposta il breadcrumb
        Theme::set('header.breadcrumbs',  $metadata->category.' > '.$metadata->title);
        
        // Renderizza il tema
        Get::theme_page('default');
    } else {
        // Torna al metodo originale per altri casi
        Get::theme_page('default', $content, $variables);
    }
}

// Funzione per generare la sidebar dei docs
function generatedocs_sidebar() {
    $documents = scanDocumentationFiles();
    $categories = [];
    
    // Define the desired category order
    $category_order = [
        'Getting started',
        'Abstracts Class',
        'Theme',
        'Dynamic Table',
        'Forms',
        'API Reference'
    ];
    
    // Raggruppa i documenti per categoria
    foreach ($documents as $doc) {
        $categories[$doc['category']][] = $doc;
    }
    
    // Sort categories according to the defined order
    uksort($categories, function($a, $b) use ($category_order) {
        $aPos = array_search($a, $category_order);
        $bPos = array_search($b, $category_order);
        
        // If both categories are in the order array, sort by their position
        if ($aPos !== false && $bPos !== false) {
            return $aPos - $bPos;
        }
        // If only one is in the order array, prioritize it
        if ($aPos !== false) return -1;
        if ($bPos !== false) return 1;
        
        // If neither is in the order array, sort alphabetically
        return strcmp($a, $b);
    });
    
    $docs_sidebar = '<div class="docs-sidebar border-end p-3">';
    
    // Aggiungi la casella di ricerca
    $docs_sidebar .= '<div class="docs-search mb-3">
        <input type="text" 
               class="form-control" 
               id="docsSearchInput"
               placeholder="Cerca nella documentazione..." 
               onkeyup="filterDocs(this.value)">
        <small class="text-muted mt-1 d-block" id="searchResultCount"></small>
    </div>';
    
    // Genera la lista delle categorie e documenti
    foreach ($categories as $category => $docs) {
        if ($category == 'hidden') continue;
        $categoryId = 'category-' . preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($category));
        
        $docs_sidebar .= '<div class="docs-category mb-3" data-category="' . htmlspecialchars($category) . '">';
        $docs_sidebar .= '<h5 class="category-title">' . htmlspecialchars($category) . '</h5>';
        $docs_sidebar .= '<ul class="nav flex-column" id="' . $categoryId . '">';
        
        foreach ($docs as $doc) {
            $url = '?page=docs&action=' . str_replace('.php', '', $doc['path']);
            $isActive = Route::compare_query_url($url);
            $activeClass = $isActive ? ' doc-link-active' : '';
            $searchData = htmlspecialchars(json_encode([
                'title' => $doc['title'],
                'tags' => $doc['tags'],
                'search' => $doc['search_text']
            ]));
            
            $docs_sidebar .= '<li class="nav-item doc-link' . $activeClass . '" data-search="' . $searchData . '">';
            $docs_sidebar .= '<a class="doc-link' . $activeClass . '" href="' . Route::url($url) . '">';
            $docs_sidebar .= htmlspecialchars($doc['title']);
 
            $docs_sidebar .= '</a>';
            $docs_sidebar .= '</li>';
        }
        
        $docs_sidebar .= '</ul>';
        $docs_sidebar .= '</div>';
    }

    $docs_sidebar .= '<a href="https://milkadmin.org/docs" target="_blank" rel="noopener noreferrer" class="text-muted">API Documentation</a> ';

    $docs_sidebar .= '</div>';
 
    return $docs_sidebar;
}

// Route principale
Route::set('docs', function() {
    Theme::set('javascript', Route::url().'/modules/docs/assets/docs.js');
    Theme::set('styles', Route::url().'/modules/docs/assets/docs.css');
    
    if (Permissions::check('_user.is_admin', 'docs')) {
        if (!isset($_REQUEST['action'])) {
            Route::redirect('?page=docs&action=modules/docs/pages/introduction.page');
        }
        
        Theme::set('header.title', 'Milk Admin Documentation');
        $action = str_replace("..", "", ($_REQUEST['action'] ?? 'modules/docs/pages/introduction.page'));
        
        if (!is_file(MILK_DIR . "/" . $action.'.php')) {
            Get::theme_page('404', '', 'Page not found: '.$action);
            return;
        }
        
        // Usa la nostra funzione personalizzata invece di Get::theme_page
        load_docs_page_with_sidebar(MILK_DIR . "/" . $action.'.php');
    } else {
        $queryString = Route::get_query_string();
        Route::redirect('?page=deny&redirect='.Route::urlsafeB64Encode($queryString));
    }
});

// Hook per aggiungere il link alla documentazione nel menu
Hooks::set('init', function($page) {
    if (Permissions::check('_user.is_admin', 'docs')) {
        Theme::set('sidebar.links', [
            'url' => Route::url('?page=docs&action=modules/docs/pages/introduction.page'), 
            'title' => 'Documentation', 
            'icon' => 'bi bi-book', 
            'order' => 90
        ]);
    }
});