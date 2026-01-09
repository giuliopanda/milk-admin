<?php
namespace Builders;

use App\Route;

!defined('MILK_DIR') && die();

/**
 * LinksBuilder - Semplice builder per creare link orizzontali
 *
 * @package Builders
 */
class LinksBuilder {
    private $links = [];
    private $groups = [];
    private $currentLink = null;
    private $currentGroup = null;
    private $elementAttributes = [
        'nav' => [],
        'ul' => [],
        'li' => [],
        'a' => [],
        'active' => [],
        'disabled' => []
    ];
    private $options = [
        'show_search' => false,
        'search_placeholder' => 'Cerca...',
        'search_input_id' => 'searchInput',
        'search_result_id' => 'searchResultCount',
        'external_links' => [],
        'container_class' => '',
        'container_attributes' => [],
        'container_id' => ''
    ];

    /**
     * Aggiunge un link
     *
     * @param string $title Titolo del link
     * @param string $url URL del link
     * @return self
     */
    public function add(string $title, string $url = '#'): self {
        if (substr($url, 0, 1) == '?' || is_array($url)) {
            $url = Route::url($url);
        }
        // Leave # URLs as-is for anchors and Bootstrap tabs
        $link = [
            'title' => $title,
            'url' => $url,
            'icon' => '',
            'active' => false,
            'disabled' => false,
            'fetch' => false,
            'params' => [],
            'group' => $this->currentGroup
        ];
        
        $this->links[] = $link;
        $this->currentLink = count($this->links) - 1;
        
        // Aggiungi il link al gruppo corrente se presente
        if ($this->currentGroup !== null) {
            $this->groups[$this->currentGroup]['links'][] = count($this->links) - 1;
        }
        
        return $this;
    }

    /**
     * Aggiunge un gruppo di link
     *
     * @param string $name Nome del gruppo
     * @param string $title Titolo visibile del gruppo (opzionale)
     * @return self
     */
    public function addGroup(string $name, string $title = ''): self {
        $this->groups[$name] = [
            'title' => $title ?: $name,
            'links' => [],
            'options' => []
        ];
        $this->currentGroup = $name;
        return $this;
    }

    /**
     * Aggiunge multipli link da un array
     *
     * @param array $links Array di link con struttura ['title', 'url', 'options'...]
     * @return self
     */
    public function addMany(array $links): self {
        foreach ($links as $link) {
            $title = $link['title'] ?? $link[0] ?? '';
            $url = $link['url'] ?? $link[1] ?? '#';
            
            $this->add($title, $url);
            
            // Applica le opzioni se presenti
            if (isset($link['icon'])) $this->icon($link['icon']);
            if (isset($link['active']) && $link['active']) $this->active();
            if (isset($link['disabled']) && $link['disabled']) $this->disable();
            if (isset($link['fetch'])) $this->fetch($link['fetch']);
            if (isset($link['params']) && is_array($link['params'])) {
                foreach ($link['params'] as $key => $value) {
                    $this->setParam($key, $value);
                }
            }
        }
        return $this;
    }

    /**
     * Imposta opzioni globali per il rendering
     *
     * @param array $options Array di opzioni
     * @return self
     */
    public function setOptions(array $options): self {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Abilita la ricerca
     *
     * @param string $placeholder Placeholder per il campo di ricerca
     * @param string $inputId ID dell'input di ricerca
     * @param string $resultId ID del contatore risultati
     * @return self
     */
    public function enableSearch(string $placeholder = 'Cerca...', string $inputId = 'searchInput', string $resultId = 'searchResultCount'): self {
        $this->options['show_search'] = true;
        $this->options['search_placeholder'] = $placeholder;
        $this->options['search_input_id'] = $inputId;
        $this->options['search_result_id'] = $resultId;
        return $this;
    }

    /**
     * Aggiunge link esterni
     *
     * @param array $links Array di link esterni
     * @return self
     */
    public function addExternalLinks(array $links): self {
        $this->options['external_links'] = array_merge($this->options['external_links'], $links);
        return $this;
    }

    /**
     * Imposta la classe del container
     *
     * @param string $class Classe CSS del container
     * @return self
     */
    public function setContainerClass(string $class): self {
        $this->options['container_class'] = $class;
        return $this;
    }

    /**
     * Imposta l'ID del container per la ricerca
     *
     * @param string $id ID del container (deve iniziare con una lettera)
     * @return self
     */
    public function setContainerId(string $id): self {
        if (!preg_match('/^[a-zA-Z]/', $id)) {
            throw new \InvalidArgumentException('Container ID must start with a letter');
        }
        $this->options['container_id'] = $id;
        return $this;
    }

    /**
     * Genera un ID unico per il container
     *
     * @return string
     */
    private function generateContainerId(): string {
        if (!empty($this->options['container_id'])) {
            return $this->options['container_id'];
        }
        
        // Genera un ID random che inizia con una lettera
        $letters = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $id = $letters[rand(0, 25)]; // Prima lettera
        
        // Aggiungi 7 caratteri random (lettere + numeri)
        for ($i = 0; $i < 7; $i++) {
            $chars = $letters . $numbers;
            $id .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        $this->options['container_id'] = 'linksBuilder' . ucfirst($id);
        return $this->options['container_id'];
    }

    /**
     * Aggiunge un'icona al link corrente
     *
     * @param string $icon Classe CSS dell'icona
     * @return self
     */
    public function icon(string $icon): self {
        if ($this->currentLink !== null) {
            $this->links[$this->currentLink]['icon'] = $icon;
        }
        return $this;
    }

    /**
     * Marca il link corrente come attivo
     *
     * @return self
     */
    public function active(): self {
        if ($this->currentLink !== null) {
            $this->links[$this->currentLink]['active'] = true;
        }
        return $this;
    }

    /**
     * Disabilita il link corrente
     *
     * @return self
     */
    public function disable(): self {
        if ($this->currentLink !== null) {
            $this->links[$this->currentLink]['disabled'] = true;
        }
        return $this;
    }

    /**
     * Abilita il fetch per il link corrente
     * Trasforma il link in una chiamata fetch asincrona tramite data-fetch
     *
     * @param string $method Metodo HTTP: 'get' o 'post' (default: 'post')
     * @return self
     */
    public function fetch(string $method = 'post'): self {
        if ($this->currentLink !== null) {
            $method = strtolower($method);
            if (in_array($method, ['get', 'post'])) {
                $this->links[$this->currentLink]['fetch'] = $method;
            }
        }
        return $this;
    }

    /**
     * Aggiunge un parametro custom al link corrente
     *
     * @param string $name Nome del parametro
     * @param mixed $value Valore del parametro
     * @return self
     */
    public function setParam(string $name, $value): self {
        if ($this->currentLink !== null) {
            $this->links[$this->currentLink]['params'][$name] = $value;
        }
        return $this;
    }

    /**
     * Imposta attributi per l'elemento nav
     *
     * @param array $attributes Attributi HTML
     * @return self
     */
    public function setNavAttributes(array $attributes): self {
        $this->elementAttributes['nav'] = array_merge($this->elementAttributes['nav'], $attributes);
        return $this;
    }

    /**
     * Imposta attributi per l'elemento ul
     *
     * @param array $attributes Attributi HTML
     * @return self
     */
    public function setUlAttributes(array $attributes): self {
        $this->elementAttributes['ul'] = array_merge($this->elementAttributes['ul'], $attributes);
        return $this;
    }

    /**
     * Imposta attributi per l'elemento li
     *
     * @param array $attributes Attributi HTML
     * @return self
     */
    public function setLiAttributes(array $attributes): self {
        $this->elementAttributes['li'] = array_merge($this->elementAttributes['li'], $attributes);
        return $this;
    }

    /**
     * Imposta attributi per l'elemento a
     *
     * @param array $attributes Attributi HTML
     * @return self
     */
    public function setAAttributes(array $attributes): self {
        $this->elementAttributes['a'] = array_merge($this->elementAttributes['a'], $attributes);
        return $this;
    }

    /**
     * Imposta attributi per elementi attivi
     *
     * @param array $attributes Attributi HTML per elementi attivi
     * @return self
     */
    public function setActiveAttributes(array $attributes): self {
        $this->elementAttributes['active'] = array_merge($this->elementAttributes['active'], $attributes);
        return $this;
    }

    /**
     * Imposta attributi per elementi disabilitati
     *
     * @param array $attributes Attributi HTML per elementi disabilitati
     * @return self
     */
    public function setDisabledAttributes(array $attributes): self {
        $this->elementAttributes['disabled'] = array_merge($this->elementAttributes['disabled'], $attributes);
        return $this;
    }

    /**
     * Verifica se un link è attivo
     */
    private function isActive(array $link): bool {
        if (isset($link['active']) && $link['active']) {
            return true;
        }
        return Route::comparePageUrl($link['url'], [], true);
    }

    /**
     * Sostituisce le variabili %variable% negli attributi con i parametri del link
     *
     * @param string $value Valore da processare
     * @param array $params Parametri del link
     * @return string
     */
    private function replaceVariables(string $value, array $params): string {
        return preg_replace_callback('/%([^%]+)%/', function($matches) use ($params) {
            $key = $matches[1];
            return isset($params[$key]) ? $params[$key] : $matches[0];
        }, $value);
    }

    /**
     * Converte array di attributi in stringa HTML
     *
     * @param array $attributes Attributi
     * @param array $params Parametri per sostituzione variabili
     * @return string
     */
    private function buildAttributes(array $attributes, array $params = []): string {
        $result = [];
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) continue;
            if ($value === true) {
                $result[] = $key;
            } else {
                $value = $this->replaceVariables((string)$value, $params);
                $result[] = $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
            }
        }

        // Aggiungi automaticamente gli attributi data-* dai parametri se presenti
        if (!empty($params)) {
            foreach ($params as $paramKey => $paramValue) {
                // Converti parametri speciali in attributi data-*
                // Ad esempio: 'search_data' diventa 'data-search'
                if (strpos($paramKey, '_data') !== false || strpos($paramKey, 'data_') !== false) {
                    $dataAttr = 'data-' . str_replace('_data', '', str_replace('data_', '', $paramKey));
                    // Evita duplicati
                    if (!isset($attributes[$dataAttr])) {
                        $value = is_array($paramValue) ? json_encode($paramValue) : (string)$paramValue;
                        $result[] = $dataAttr . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
                    }
                }
            }
        }

        return empty($result) ? '' : ' ' . implode(' ', $result);
    }

    /**
     * Ottiene gli attributi per un elemento specifico
     *
     * @param string $element Nome dell'elemento (nav, ul, li, a)
     * @param array $defaults Attributi di default
     * @param array $params Parametri per sostituzione variabili
     * @param bool $isActive Se l'elemento è attivo
     * @param bool $isDisabled Se l'elemento è disabilitato
     * @param array|null $link Link completo per accedere a fetch
     * @return string
     */
    private function getElementAttributes(string $element, array $defaults = [], array $params = [], bool $isActive = false, bool $isDisabled = false, ?array $link = null): string {
        $attributes = $defaults;

        if (isset($this->elementAttributes[$element]) && !empty($this->elementAttributes[$element])) {
            $attributes = array_merge($this->elementAttributes[$element], $attributes);
        }

        if ($isActive && isset($this->elementAttributes['active']) && !empty($this->elementAttributes['active'])) {
            $attributes = array_merge($this->elementAttributes['active'], $attributes);
        }

        if ($isDisabled && isset($this->elementAttributes['disabled']) && !empty($this->elementAttributes['disabled'])) {
            $attributes = array_merge($this->elementAttributes['disabled'], $attributes);
        }

        // Aggiungi data-fetch solo per elementi <a> se il link ha fetch attivo
        if ($element === 'a' && $link !== null && !empty($link['fetch'])) {
            $attributes['data-fetch'] = $link['fetch'];
        }

        return $this->buildAttributes($attributes, $params);
    }

    /**
     * Render navbar style
     */
    private function renderNavbar(): string {
        if (empty($this->links)) return '';

        $ulDefaults = ['class' => 'nav my-2 justify-content-center my-md-0 text-small d-none d-lg-flex'];
        $ulAttrs = $this->getElementAttributes('ul', $ulDefaults);

        ob_start();
        ?>
        <ul<?php echo $ulAttrs; ?>>
            <?php foreach ($this->links as $link): ?>
                <?php 
                $isActive = $this->isActive($link);
                $isDisabled = $link['disabled'];
                $liAttrs = $this->getElementAttributes('li', [], $link['params'], $isActive, $isDisabled);
                ?>
                <li<?php echo $liAttrs; ?>>
                    <?php if ($link['disabled']): ?>
                        <?php $spanAttrs = $this->getElementAttributes('a', ['class' => 'nav-link disabled'], $link['params'], $isActive, $isDisabled, $link); ?>
                        <span<?php echo $spanAttrs; ?>>
                            <?php if ($link['icon']): ?>
                                <i class="<?php _p($link['icon']); ?>"></i>
                                <span class="<?php echo $link['icon'] ? 'd-none d-lg-inline' : ''; ?>"><?php _pt($link['title']); ?></span>
                            <?php else: ?>
                                <?php _pt($link['title']); ?>
                            <?php endif; ?>
                        </span>
                    <?php elseif ($this->isActive($link)): ?>
                        <?php $spanAttrs = $this->getElementAttributes('a', ['class' => 'nav-link nav-link-active'], $link['params'], $isActive, $isDisabled, $link); ?>
                        <span<?php echo $spanAttrs; ?>>
                            <?php if ($link['icon']): ?>
                                <i class="<?php _p($link['icon']); ?>"></i>
                                <span class="<?php echo $link['icon'] ? 'd-none d-lg-inline' : ''; ?>"><?php _pt($link['title']); ?></span>
                            <?php else: ?>
                                <?php _pt($link['title']); ?>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <?php $spanAttrs = $this->getElementAttributes('li', ['class' => 'nav-link'], $link['params'], $isActive, $isDisabled); ?>
                        <span<?php echo $spanAttrs; ?>>
                            <?php $aAttrs = $this->getElementAttributes('a', ['class' => 'link-action', 'href' => $link['url']], $link['params'], $isActive, $isDisabled, $link); ?>
                            <a<?php echo $aAttrs; ?>>
                                <?php if ($link['icon']): ?>
                                    <i class="<?php _p($link['icon']); ?>"></i>
                                    <span class="<?php echo $link['icon'] ? 'd-none d-lg-inline' : ''; ?>"><?php _pt($link['title']); ?></span>
                                <?php else: ?>
                                    <?php _pt($link['title']); ?>
                                <?php endif; ?>
                            </a>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    /**
     * Render breadcrumb style
     */
    private function renderBreadcrumb(): string {
        if (empty($this->links)) return '';

        $navDefaults = ['aria-label' => 'breadcrumb'];
        $navAttrs = $this->getElementAttributes('nav', $navDefaults);
        
        $ulDefaults = ['class' => 'breadcrumb'];
        $ulAttrs = $this->getElementAttributes('ul', $ulDefaults);

        ob_start();
        ?>
        <nav<?php echo $navAttrs; ?>>
            <ul<?php echo $ulAttrs; ?>>
                <?php foreach ($this->links as $index => $link): ?>
                    <?php 
                    $isActive = $this->isActive($link) || $index === count($this->links) - 1;
                    $isDisabled = $link['disabled'];
                    ?>
                    <?php if ($isActive): ?>
                        <?php $liAttrs = $this->getElementAttributes('li', ['class' => 'breadcrumb-item active', 'aria-current' => 'page'], $link['params'], $isActive, $isDisabled); ?>
                        <li<?php echo $liAttrs; ?>>
                            <?php if ($link['icon']): ?><i class="<?php _p($link['icon']); ?>"></i> <?php endif; ?>
                            <?php _pt($link['title']); ?>
                        </li>
                    <?php else: ?>
                        <?php $liAttrs = $this->getElementAttributes('li', ['class' => 'breadcrumb-item'], $link['params'], $isActive, $isDisabled); ?>
                        <li<?php echo $liAttrs; ?>>
                            <?php if ($link['disabled']): ?>
                                <?php $spanAttrs = $this->getElementAttributes('a', ['class' => 'disabled'], $link['params'], $isActive, $isDisabled, $link); ?>
                                <span<?php echo $spanAttrs; ?>>
                                    <?php if ($link['icon']): ?><i class="<?php _p($link['icon']); ?>"></i> <?php endif; ?>
                                    <?php _pt($link['title']); ?>
                                </span>
                            <?php else: ?>
                                <?php $aAttrs = $this->getElementAttributes('a', ['class' => 'link-action', 'href' => $link['url']], $link['params'], $isActive, $isDisabled, $link); ?>
                                <a<?php echo $aAttrs; ?>>
                                    <?php if ($link['icon']): ?><i class="<?php _p($link['icon']); ?>"></i> <?php endif; ?>
                                    <?php _pt($link['title']); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
        return ob_get_clean();
    }


    /**
     * Render vertical style (sidebar navigation)
     */
    private function renderVertical(): string {
        if (empty($this->links)) return '';

        $groupedLinks = $this->getGroupedLinks();
        
        // Se è un singolo gruppo default, rendere come lista semplice
        if (count($groupedLinks) === 1 && isset($groupedLinks['default'])) {
            return $this->renderVerticalSimple($groupedLinks['default']['links']);
        }
        
        // Render con gruppi multipli
        $html = '';
        foreach ($groupedLinks as $groupName => $group) {
          
            if ($groupName === 'hidden') continue;
            
            if (!empty($group['title']) && $groupName !== 'default') {
                $html .= '<div class="group-section mb-3">';
                $html .= '<h6 class="group-title text-muted">' . htmlspecialchars($group['title']) . '</h6>';
                $html .= $this->renderVerticalSimple($group['links']);
                $html .= '</div>';
            } else {
                $html .= $this->renderVerticalSimple($group['links']);
            }
        }
        
        return $html;
    }

    /**
     * Render vertical semplice per un singolo gruppo di link
     */
    private function renderVerticalSimple(array $links): string {
        $ulDefaults = ['class' => 'nav flex-column'];
        $ulAttrs = $this->getElementAttributes('ul', $ulDefaults);

        ob_start();
        ?>
        <ul<?php echo $ulAttrs; ?>>
            <?php foreach ($links as $link): ?>
                <?php
                $isActive = $this->isActive($link);
                $isDisabled = $link['disabled'];

                // Prepara gli attributi li con i data-* params
                $liDefaults = ['class' => 'nav-item'];
                if ($isActive) {
                    $liDefaults['class'] = 'nav-item doc-link-active';
                } elseif (!$isDisabled) {
                    $liDefaults['class'] = 'nav-item doc-link';
                }
                ?>
                <?php if ($link['disabled']): ?>
                    <?php $liAttrs = $this->getElementAttributes('li', $liDefaults, $link['params'], $isActive, $isDisabled); ?>
                    <li<?php echo $liAttrs; ?>>
                        <?php $spanAttrs = $this->getElementAttributes('a', ['class' => 'disabled'], $link['params'], $isActive, $isDisabled, $link); ?>
                        <span<?php echo $spanAttrs; ?>>
                            <?php if ($link['icon']): ?><i class="<?php _p($link['icon']); ?>"></i> <?php endif; ?>
                            <?php _pt($link['title']); ?>
                        </span>
                    </li>
                <?php elseif ($this->isActive($link)): ?>
                    <?php $liAttrs = $this->getElementAttributes('li', $liDefaults, $link['params'], $isActive, $isDisabled); ?>
                    <li<?php echo $liAttrs; ?>>
                        <?php $aAttrs = $this->getElementAttributes('a', ['class' => 'doc-link doc-link-active', 'href' => $link['url']], $link['params'], $isActive, $isDisabled, $link); ?>
                        <a<?php echo $aAttrs; ?>>
                            <?php if ($link['icon']): ?><i class="<?php _p($link['icon']); ?>"></i> <?php endif; ?>
                            <?php _pt($link['title']); ?>
                        </a>
                    </li>
                <?php else: ?>
                    <?php $liAttrs = $this->getElementAttributes('li', $liDefaults, $link['params'], $isActive, $isDisabled); ?>
                    <li<?php echo $liAttrs; ?>>
                        <?php
                        $aAttrs = $this->getElementAttributes('a', ['class' => 'doc-link', 'href' => $link['url']], $link['params'], $isActive, $isDisabled, $link);
                         ?>
                        <a<?php echo $aAttrs; ?>>
                            <?php if ($link['icon']): ?><i class="<?php _p($link['icon']); ?>"></i> <?php endif; ?>
                            <?php _pt($link['title']); ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    /**
     * Render tabs style
     */
    private function renderTabs(): string {
        if (empty($this->links)) return '';

        $ulDefaults = ['class' => 'nav nav-tabs'];
        $ulAttrs = $this->getElementAttributes('ul', $ulDefaults);

        ob_start();
        ?>
        <ul<?php echo $ulAttrs; ?>>
            <?php foreach ($this->links as $link): ?>
                <?php
                $isActive = $this->isActive($link);
                $isDisabled = $link['disabled'];
                $liAttrs = $this->getElementAttributes('li', ['class' => 'nav-item'], $link['params'], $isActive, $isDisabled);
                ?>
                <li<?php echo $liAttrs; ?>>
                    <?php if ($link['disabled']): ?>
                        <?php $spanAttrs = $this->getElementAttributes('a', ['class' => 'nav-link disabled'], $link['params'], $isActive, $isDisabled, $link); ?>
                        <span<?php echo $spanAttrs; ?>>
                            <?php if ($link['icon']): ?><i class="<?php _p($link['icon']); ?>"></i> <?php endif; ?>
                            <?php _pt($link['title']); ?>
                        </span>
                    <?php else: ?>
                        <?php
                        $activeClass = $this->isActive($link) ? ' active' : '';
                        $aAttrs = $this->getElementAttributes('a', ['class' => 'nav-link' . $activeClass, 'href' => $link['url']], $link['params'], $isActive, $isDisabled, $link);
                        ?>
                        <a<?php echo $aAttrs; ?>>
                            <?php if ($link['icon']): ?><i class="<?php _p($link['icon']); ?>"></i> <?php endif; ?>
                            <?php _pt($link['title']); ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    /**
     * Render pills style
     */
    private function renderPills(): string {
        if (empty($this->links)) return '';

        $ulDefaults = ['class' => 'nav nav-pills d-flex align-items-center'];
        $ulAttrs = $this->getElementAttributes('ul', $ulDefaults);

        ob_start();
        ?>
        <ul<?php echo $ulAttrs; ?>>
            <?php foreach ($this->links as $link): ?>
                <?php
                $isActive = $this->isActive($link);
                $isDisabled = $link['disabled'];
                $liAttrs = $this->getElementAttributes('li', ['class' => 'nav-item'], $link['params'], $isActive, $isDisabled);
                ?>
                <li<?php echo $liAttrs; ?>>
                    <?php if ($link['disabled']): ?>
                        <?php $aAttrs = $this->getElementAttributes('a', ['class' => 'nav-link disabled', 'tabindex' => '-1', 'aria-disabled' => 'true'], $link['params'], $isActive, $isDisabled, $link); ?>
                        <a<?php echo $aAttrs; ?>>
                            <?php if ($link['icon']): ?>
                                <i class="<?php _p($link['icon']); ?>"></i>
                                <span class="<?php echo $link['icon'] ? 'd-none d-lg-inline ms-1' : ''; ?>"><?php _pt($link['title']); ?></span>
                            <?php else: ?>
                                <?php _pt($link['title']); ?>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <?php
                        $activeClass = $this->isActive($link) ? ' active' : '';
                        $aAttrs = $this->getElementAttributes('a', ['class' => 'nav-link' . $activeClass, 'href' => $link['url']], $link['params'], $isActive, $isDisabled, $link);
                        ?>
                        <a<?php echo $aAttrs; ?>>
                            <?php if ($link['icon']): ?>
                                <i class="<?php _p($link['icon']); ?>"></i>
                                <span class="<?php echo $link['icon'] ? 'd-none d-lg-inline ms-1' : ''; ?>"><?php _pt($link['title']); ?></span>
                            <?php else: ?>
                                <?php _pt($link['title']); ?>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    /**
     * Genera l'HTML
     *
     * @param string $style Stile di rendering: 'navbar', 'breadcrumb', 'tabs', 'pills', 'vertical', 'sidebar'
     * @return string
     */
    public function render(string $style = 'navbar'): string {
        // Include il JavaScript se ci sono funzionalità di ricerca attive
        if ($this->options['show_search']) {
            $this->includeJavaScript();
        }

        return match($style) {
            'breadcrumb' => $this->renderBreadcrumb(),
            'tabs' => $this->renderTabs(),
            'pills' => $this->renderPills(),
            'vertical' => $this->renderVertical(),
            'sidebar' => $this->renderSidebar(),
            default => $this->renderNavbar()
        };
    }

    /**
     * Include il file JavaScript per LinksBuilder se non già incluso
     */
    private function includeJavaScript(): void {
        static $jsIncluded = false;
        
        if (!$jsIncluded && class_exists('App\\Theme')) {
            // Usa il sistema Theme di MilkAdmin per includere il JS
            \App\Theme::set('javascript.linksBuilder', \App\Route::url() . '/theme/assets/linksBuilder.js');
            $jsIncluded = true;
        }
    }

    /**
     * Ottiene tutti i link organizzati per gruppo
     */
    private function getGroupedLinks(): array {
        if (empty($this->groups)) {
            // Se non ci sono gruppi, restituisci tutti i link in un unico gruppo default
            return ['default' => ['title' => '', 'links' => $this->links]];
        }
        
        $grouped = [];
        foreach ($this->groups as $groupName => $group) {
            $grouped[$groupName] = [
                'title' => $group['title'],
                'links' => array_map(fn($index) => $this->links[$index], $group['links'])
            ];
        }
        
        return $grouped;
    }

    /**
     * Genera il search box se abilitato
     */
    private function generateSearchBox(): string {
        if (!$this->options['show_search']) {
            return '';
        }
        
        $containerId = $this->generateContainerId();
        
        return '<div class="search-box mb-3">
            <input type="text" 
                   class="form-control" 
                   id="' . $this->options['search_input_id'] . '"
                   placeholder="' . $this->options['search_placeholder'] . '" 
                   onkeyup="filterLinks(this.value, \'' . $containerId . '\')">
            <small class="text-body-secondary mt-1 d-block" id="' . $this->options['search_result_id'] . '"></small>
        </div>';
    }

    /**
     * Genera i link esterni se presenti
     */
    private function generateExternalLinks(): string {
        if (empty($this->options['external_links'])) {
            return '';
        }
        
        $html = '';
        foreach ($this->options['external_links'] as $link) {
            $target = isset($link['target']) ? ' target="' . $link['target'] . '"' : '';
            $rel = isset($link['rel']) ? ' rel="' . $link['rel'] . '"' : '';
            $class = isset($link['class']) ? ' class="' . $link['class'] . '"' : ' class="text-body-secondary"';
            $html .= '<a href="' . $link['url'] . '"' . $target . $rel . $class . '>' . $link['title'] . '</a> ';
        }
        
        return $html;
    }

    /**
     * Render sidebar con gruppi e opzioni globali
     */
    private function renderSidebar(): string {
        $groupedLinks = $this->getGroupedLinks();

        // Container con classe personalizzata o default e ID unico
        $containerClass = $this->options['container_class'] ?: 'docs-sidebar border-end p-3';
        $containerId = $this->generateContainerId();

        // Aggiungi l'ID agli attributi del container
        $containerAttributes = array_merge($this->options['container_attributes'], ['id' => $containerId]);
        $containerAttrs = $this->buildAttributes($containerAttributes);
        $sidebar = '<div class="' . $containerClass . '"' . $containerAttrs . '>';

        // Search box
        $sidebar .= $this->generateSearchBox();

        // Generate groups/categories

        foreach ($groupedLinks as $index => $groupData) {

            $groupName = $index;
            $group = $groupData;

            if ($groupName === 'hidden') continue;

            // Se c'è un titolo di gruppo, aggiungilo (solo per sidebar)
            if (!empty($group['title']) && $groupName !== 'default') {
                $collapseId = $containerId . '_group_' . $index;

                // Mobile collapsible header - visibile solo su mobile
                $sidebar .= '<div class="group-section mb-3">';
                $sidebar .= '<h5 class="group-title d-md-block cursor-pointer" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '">';
                $sidebar .= '<span class="d-md-none">';
                $sidebar .= '<i class="bi bi-chevron-right me-2 collapse-icon"></i>';
                $sidebar .= '</span>';
                $sidebar .= htmlspecialchars($group['title']);
                $sidebar .= '</h5>';

                // Collapsible content - collapsed su mobile, sempre visibile su desktop
                $sidebar .= '<div class="collapse d-md-block" id="' . $collapseId . '">';
            } else {
                $sidebar .= '<div class="group-section mb-3">';
                $sidebar .= '<div>'; // Wrapper per mantenere la struttura
            }

            // Genera i link del gruppo usando renderVertical
            $groupBuilder = $this->createGroupBuilder($group['links']);
            $sidebar .= $groupBuilder->render('vertical');
            $sidebar .= '</div>'; // Chiude div collapsible o wrapper
            $sidebar .= '</div>'; // Chiude group-section
        }

        // External links
        $sidebar .= $this->generateExternalLinks();
        $sidebar .= '</div>';

        return $sidebar;
    }

    /**
     * Crea un LinksBuilder per un gruppo di link
     */
    private function createGroupBuilder(array $links): LinksBuilder {
        $builder = LinksBuilder::create();
        
        // Copia le configurazioni attuali
        $builder->elementAttributes = $this->elementAttributes;
        
        foreach ($links as $link) {
            $builder->add($link['title'], $link['url']);
            
            // Copia stati e attributi
            if (!empty($link['icon'])) $builder->icon($link['icon']);
            if ($link['active']) $builder->active();
            if ($link['disabled']) $builder->disable();
            
            // Copia parametri
            foreach ($link['params'] as $key => $value) {
                $builder->setParam($key, $value);
            }
        }
        
        return $builder;
    }

    /**
     * Factory method
     */
    public static function create(): self {
        return new self();
    }

    /**
     * Magic method per output diretto
     */
    public function __toString(): string {
        return $this->render();
    }
}