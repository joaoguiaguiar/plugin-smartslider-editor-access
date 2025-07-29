<?php
/**
 * Plugin Name: Permissões IBRAM – Editor com Acesso Seguro ao Smart Slider
 * Description: Acesso personalizado e seguro para o papel de Editor. O foco deste plugin é permitir o uso do Smart Slider sem necessidade de privilégios de administrador, além de garantir acesso completo a páginas, posts, menus e widgets. Todo o restante do painel é bloqueado, trazendo mais segurança para a infraestrutura do site.
 * Version: 1.2
 * Author: joao.aguiar
 * Author URI: https://github.com/joaoguiaguiar
 */


if (!defined('ABSPATH')) exit;

class IBRAM_EditorAccess {

    private $allowed_pages = [
        'index.php',                  // Painel
        'edit.php',                  // Posts
        'edit.php?post_type=page',   // Páginas
        'themes.php',                // Aparência
        'admin.php?page=smartslider' // Smart Slider
    ];

    private $allowed_submenus = [
        'nav-menus.php',   // Menus
        'widgets.php'      // Widgets
    ];

    public function __construct() {
        add_action('init', [$this, 'setup_caps']);
        add_filter('user_has_cap', [$this, 'manage_caps'], 10, 4);
        add_action('admin_menu', [$this, 'setup_menu'], 999);
        add_filter('option_page_capability_smartslider3', [$this, 'smartslider_cap']);
        add_action('load-customize.php', [$this, 'block_customizer']);
        
        // Melhorias adicionais
        add_action('admin_init', [$this, 'validate_access']);
        add_action('wp_login', [$this, 'log_editor_login']);
        add_action('admin_bar_menu', [$this, 'clean_admin_bar'], 999);
    }

    public function setup_caps() {
        $role = get_role('editor');
        if (!$role) return;

        $caps = [
            'smartslider',
            'smartslider_edit',
            'smartslider_config',
            'smartslider_delete',        // ADICIONADO: Permissão para deletar slides
            'smartslider_edit_sliders',  // ADICIONADO: Permissão específica para editar sliders
            'smartslider_delete_sliders', // ADICIONADO: Permissão específica para deletar sliders
            'edit_posts',
            'edit_pages',
            'publish_pages',
            'edit_theme_options',
            'upload_files'
        ];

        foreach ($caps as $cap) {
            if (!$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
        }
    }

    public function manage_caps($allcaps, $caps, $args, $user) {
        if (!in_array('editor', (array) $user->roles)) {
            return $allcaps;
        }

        return array_merge($allcaps, [
            'smartslider' => true,
            'smartslider_edit' => true,
            'smartslider_config' => true,
            'smartslider_delete' => true,        // ADICIONADO: Permissão para deletar slides
            'smartslider_edit_sliders' => true,  // ADICIONADO: Permissão específica para editar sliders
            'smartslider_delete_sliders' => true, // ADICIONADO: Permissão específica para deletar sliders
            'edit_posts' => true,
            'edit_pages' => true,
            'publish_pages' => true,
            'edit_theme_options' => true,
            'upload_files' => true
        ]);
    }

    public function setup_menu() {
        $user = wp_get_current_user();
        if (!in_array('editor', (array) $user->roles)) return;

        global $submenu;

        // Remove menus não permitidos
        foreach ($GLOBALS['menu'] as $id => $item) {
            if (!in_array($item[2], $this->allowed_pages)) {
                remove_menu_page($item[2]);
            }
        }

        // Submenus da Aparência: Menus e Widgets apenas
        if (isset($submenu['themes.php'])) {
            foreach ($submenu['themes.php'] as $id => $subitem) {
                if (!in_array($subitem[2], $this->allowed_submenus)) {
                    remove_submenu_page('themes.php', $subitem[2]);
                }
            }
        }
    }

    public function smartslider_cap() {
        return 'smartslider_config';
    }

    public function block_customizer() {
        if (current_user_can('editor') && !current_user_can('manage_options')) {
            $this->log_blocked_access('Customizer');
            wp_die('Acesso ao Personalizador está bloqueado por segurança.');
        }
    }

    // Validação adicional de acesso
    public function validate_access() {
        $user = wp_get_current_user();
        if (!in_array('editor', (array) $user->roles)) return;

        global $pagenow;
        
        // Páginas explicitamente bloqueadas
        $blocked_pages = [
            'update-core.php',
            'update.php',
            'plugin-install.php',
            'plugin-editor.php',
            'theme-editor.php',
            'users.php',
            'user-new.php',
            'options-general.php'
        ];

        if (in_array($pagenow, $blocked_pages)) {
            $this->log_blocked_access($pagenow);
            wp_die(
                'Acesso não permitido. Esta tentativa foi registrada.',
                'Acesso Restrito',
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    // Log de tentativas de acesso bloqueadas
    private function log_blocked_access($page) {
        $user = wp_get_current_user();
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP não disponível';
        
        error_log("IBRAM Security: Editor {$user->user_login} tentou acessar {$page} em {$timestamp} (IP: {$ip})");
    }

    // Log de login de editores
    public function log_editor_login($user_login) {
        $user = get_user_by('login', $user_login);
        if ($user && in_array('editor', (array) $user->roles)) {
            $timestamp = date('Y-m-d H:i:s');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP não disponível';
            error_log("IBRAM Access: Editor {$user_login} fez login em {$timestamp} (IP: {$ip})");
        }
    }

    // Limpa a barra de admin
    public function clean_admin_bar($wp_admin_bar) {
        $user = wp_get_current_user();
        if (!in_array('editor', (array) $user->roles)) return;

        // Remove itens desnecessários
        $wp_admin_bar->remove_node('updates');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('customize');
        $wp_admin_bar->remove_node('themes');
    }

    // Método para verificar saúde do plugin
    public function health_check() {
        $role = get_role('editor');
        if (!$role) return false;

        $required_caps = ['smartslider', 'smartslider_edit', 'smartslider_config', 'smartslider_delete'];
        foreach ($required_caps as $cap) {
            if (!$role->has_cap($cap)) {
                return false;
            }
        }
        return true;
    }
}

new IBRAM_EditorAccess();

// Ativação
register_activation_hook(__FILE__, function () {
    $role = get_role('editor');
    if ($role) {
        $caps = [
            'smartslider',
            'smartslider_edit',
            'smartslider_config',
            'smartslider_delete',        // ADICIONADO: Permissão para deletar slides
            'smartslider_edit_sliders',  // ADICIONADO: Permissão específica para editar sliders
            'smartslider_delete_sliders', // ADICIONADO: Permissão específica para deletar sliders
            'edit_theme_options'
        ];
        
        foreach ($caps as $cap) {
            $role->add_cap($cap);
        }
        
        // Log da ativação
        error_log("IBRAM Plugin: Ativado com sucesso em " . date('Y-m-d H:i:s'));
    }
});

// Desativação
register_deactivation_hook(__FILE__, function () {
    $role = get_role('editor');
    if ($role) {
        $caps = [
            'smartslider',
            'smartslider_edit',
            'smartslider_config',
            'smartslider_delete',        // ADICIONADO: Permissão para deletar slides
            'smartslider_edit_sliders',  // ADICIONADO: Permissão específica para editar sliders
            'smartslider_delete_sliders', // ADICIONADO: Permissão específica para deletar sliders
            'edit_theme_options'
        ];
        
        foreach ($caps as $cap) {
            $role->remove_cap($cap);
        }
        
        // Log da desativação
        error_log("IBRAM Plugin: Desativado em " . date('Y-m-d H:i:s'));
    }
});

// Adiciona menu de status (opcional)
add_action('admin_menu', function() {
    if (current_user_can('manage_options')) {
        add_management_page(
            'Status IBRAM Editor',
            'Status IBRAM',
            'manage_options',
            'ibram-status',
            function() {
                $plugin = new IBRAM_EditorAccess();
                $status = $plugin->health_check() ? 'Funcionando' : 'Problema detectado';
                echo "<div class='wrap'><h1>Status do Plugin IBRAM</h1>";
                echo "<p>Status: <strong>{$status}</strong></p>";
                echo "<p>Última verificação: " . date('Y-m-d H:i:s') . "</p>";
                echo "</div>";
            }
        );
    }
});
?>