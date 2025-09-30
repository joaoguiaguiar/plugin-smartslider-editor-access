<?php
/*
Plugin Name: Editor com Acesso Seguro – Permissões IBRAM
Plugin URI: https://github.com/joaoguiaguiar/plugin-smartslider-ibram
Description: Dá ao Editor permissão pro Smart Slider, páginas, posts, menus e widgets. Resto do painel fica bloqueado.
Version: 1.2.0
Author: CTINF / joao.aguiar
Author URI: https://github.com/joaoguiaguiar
Text Domain: ibram-permissoes
License: GPL v2 or later
*/

if (!defined('ABSPATH')) exit;

class IBRAM_AcessoEditor {

    // Páginas que o editor pode abrir
    private $paginas_permitidas = [
        'index.php',                   // Painel inicial
        'edit.php',                    // Posts
        'edit.php?post_type=page',     // Páginas
        'themes.php',                  // Aparência
        'admin.php?page=smartslider'   // Smart Slider
    ];

    // Submenus que o editor pode abrir dentro de Aparência
    private $submenus_permitidos = [
        'nav-menus.php',   // Menus
        'widgets.php'      // Widgets
    ];

    public function __construct() {
        add_action('init', [$this, 'definir_permissoes']);
        add_filter('user_has_cap', [$this, 'forcar_permissoes'], 10, 4);
        add_action('admin_menu', [$this, 'ajustar_menus'], 999);
        add_filter('option_page_capability_smartslider3', [$this, 'permissao_smartslider']);
        add_action('load-customize.php', [$this, 'bloquear_personalizador']);

        // Extras
        add_action('admin_init', [$this, 'validar_acesso']);
        add_action('wp_login', [$this, 'registrar_login_editor']);
        add_action('admin_bar_menu', [$this, 'limpar_barra_admin'], 999);
    }

    // Dá as permissões básicas ao papel de editor
    public function definir_permissoes() {
        $editor = get_role('editor');
        if (!$editor) return;

        $permissoes = [
            'smartslider',
            'smartslider_edit',
            'smartslider_config',
            'smartslider_delete',
            'smartslider_edit_sliders',
            'smartslider_delete_sliders',
            'edit_posts',
            'edit_pages',
            'publish_pages',
            'edit_theme_options',
            'upload_files'
        ];

        foreach ($permissoes as $p) {
            if (!$editor->has_cap($p)) {
                $editor->add_cap($p);
            }
        }
    }

    // Garante que os editores sempre tenham as permissões necessárias
    public function forcar_permissoes($todas, $solicitadas, $args, $usuario) {
        if (!in_array('editor', (array) $usuario->roles)) {
            return $todas;
        }

        return array_merge($todas, [
            'smartslider' => true,
            'smartslider_edit' => true,
            'smartslider_config' => true,
            'smartslider_delete' => true,
            'smartslider_edit_sliders' => true,
            'smartslider_delete_sliders' => true,
            'edit_posts' => true,
            'edit_pages' => true,
            'publish_pages' => true,
            'edit_theme_options' => true,
            'upload_files' => true
        ]);
    }

    // Remove menus que o editor não deve ver
    public function ajustar_menus() {
        $usuario = wp_get_current_user();
        if (!in_array('editor', (array) $usuario->roles)) return;

        global $submenu;

        // Menus principais
        foreach ($GLOBALS['menu'] as $item) {
            if (!in_array($item[2], $this->paginas_permitidas)) {
                remove_menu_page($item[2]);
            }
        }

        // Submenus de aparência
        if (isset($submenu['themes.php'])) {
            foreach ($submenu['themes.php'] as $subitem) {
                if (!in_array($subitem[2], $this->submenus_permitidos)) {
                    remove_submenu_page('themes.php', $subitem[2]);
                }
            }
        }
    }

    public function permissao_smartslider() {
        return 'smartslider_config';
    }

    // Bloqueia o Personalizador (customize.php)
    public function bloquear_personalizador() {
        if (current_user_can('editor') && !current_user_can('manage_options')) {
            $this->registrar_bloqueio('Personalizador');
            wp_die('Acesso ao Personalizador está bloqueado por segurança.');
        }
    }

    // Bloqueia acessos a páginas sensíveis
    public function validar_acesso() {
        $usuario = wp_get_current_user();
        if (!in_array('editor', (array) $usuario->roles)) return;

        global $pagenow;

        $bloqueadas = [
            'update-core.php',
            'update.php',
            'plugin-install.php',
            'plugin-editor.php',
            'theme-editor.php',
            'users.php',
            'user-new.php',
            'options-general.php'
        ];

        if (in_array($pagenow, $bloqueadas)) {
            $this->registrar_bloqueio($pagenow);
            wp_die(
                'Acesso não permitido. Esta tentativa foi registrada.',
                'Acesso Restrito',
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    // Registro de acessos bloqueados
    private function registrar_bloqueio($pagina) {
        $usuario = wp_get_current_user();
        $quando = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP não disponível';
        error_log("IBRAM Segurança: Editor {$usuario->user_login} tentou acessar {$pagina} em {$quando} (IP: {$ip})");
    }

    // Registro de login de editores
    public function registrar_login_editor($login) {
        $usuario = get_user_by('login', $login);
        if ($usuario && in_array('editor', (array) $usuario->roles)) {
            $quando = date('Y-m-d H:i:s');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP não disponível';
            error_log("IBRAM Acesso: Editor {$login} fez login em {$quando} (IP: {$ip})");
        }
    }

    // Remove itens inúteis da barra superior
    public function limpar_barra_admin($barra) {
        $usuario = wp_get_current_user();
        if (!in_array('editor', (array) $usuario->roles)) return;

        $barra->remove_node('updates');
        $barra->remove_node('comments');
        $barra->remove_node('customize');
        $barra->remove_node('themes');
    }

    // Testa se o plugin está funcionando
    public function checar_status() {
        $editor = get_role('editor');
        if (!$editor) return false;

        $necessarias = ['smartslider', 'smartslider_edit', 'smartslider_config', 'smartslider_delete'];
        foreach ($necessarias as $p) {
            if (!$editor->has_cap($p)) return false;
        }
        return true;
    }
}

new IBRAM_AcessoEditor();

// Ativação
register_activation_hook(__FILE__, function () {
    $editor = get_role('editor');
    if ($editor) {
        $permissoes = [
            'smartslider',
            'smartslider_edit',
            'smartslider_config',
            'smartslider_delete',
            'smartslider_edit_sliders',
            'smartslider_delete_sliders',
            'edit_theme_options'
        ];
        foreach ($permissoes as $p) {
            $editor->add_cap($p);
        }
        error_log("IBRAM Plugin: Ativado em " . date('Y-m-d H:i:s'));
    }
});

// Desativação
register_deactivation_hook(__FILE__, function () {
    $editor = get_role('editor');
    if ($editor) {
        $permissoes = [
            'smartslider',
            'smartslider_edit',
            'smartslider_config',
            'smartslider_delete',
            'smartslider_edit_sliders',
            'smartslider_delete_sliders',
            'edit_theme_options'
        ];
        foreach ($permissoes as $p) {
            $editor->remove_cap($p);
        }
        error_log("IBRAM Plugin: Desativado em " . date('Y-m-d H:i:s'));
    }
});

// Página de status no admin
add_action('admin_menu', function() {
    if (current_user_can('manage_options')) {
        add_management_page(
            'Status IBRAM Editor',
            'Status IBRAM',
            'manage_options',
            'ibram-status',
            function() {
                $plugin = new IBRAM_AcessoEditor();
                $status = $plugin->checar_status() ? 'Funcionando' : 'Problema detectado';
                echo "<div class='wrap'><h1>Status do Plugin IBRAM</h1>";
                echo "<p>Status: <strong>{$status}</strong></p>";
                echo "<p>Última verificação: " . date('Y-m-d H:i:s') . "</p>";
                echo "</div>";
            }
        );
    }
});