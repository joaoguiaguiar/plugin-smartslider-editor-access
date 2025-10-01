# Plugin Smart Slider Editor Access

## 🧭 Desafio Encontrado Pós-Deploy

Após o desenvolvimento completo do tema e instalação dos plugins nos sites oficiais, surgiu um desafio após a definição de que os usuários dos museus não teriam mais acesso como administradores, mas sim como editores.

A princípio, parecia algo simples ajustar os papéis e permissões no WordPress mas logo identifiquei um bloqueio importante: o plugin Smart Slider 3, que usamos intensamente na publicação de conteúdos visuais, simplesmente não funciona corretamente para o papel de Editor.

Mesmo adicionando manualmente todas as capacidades relacionadas ao plugin (como `smartslider_edit`, `smartslider_edit_sliders`, etc.), o menu continuava oculto ou incompleto.

## 🧪 Tentativa Inicial

Minha primeira abordagem foi tentar "driblar" essa limitação, atribuindo diretamente as permissões específicas do plugin ao papel de Editor. Porém, isso não funcionou. O motivo?

## 🔒 Limitação Interna do Plugin

O Smart Slider faz verificações internas com:

```php
if (!current_user_can('manage_options')) {
    // Bloqueia acesso
}
```

Ou seja: mesmo que o Editor tenha todas as outras permissões, se ele não tiver `manage_options`, o plugin simplesmente não libera o acesso total. E essa capability é exclusiva de administradores — o que foge da política atual de segurança dos sites.

Sendo assim essa tentativa demonstrou ser inviável para a solução.

Além disso tentei criar um novo papel (`editor_slider`) mas também não ajudava. Por que?

Criar um papel novo (`add_role('editor_slider')`) exige que você:

- Crie o papel ✅
- Atribua usuários a esse papel ❗️(você fez com `migrar_usuarios_para_editor_slider`)
- E ainda assim... o WordPress e o plugin esperam `editor` ou `administrator`. Se você usar um papel customizado, nem sempre os filtros, menus e plugins se comportam como esperado, a não ser que você altere tudo.

Por isso, usar `editor_slider` até poderia funcionar, mas exigia MUITO mais gambiarra do que adaptar o papel editor.

## 🛠️ Solução Desenvolvida

A solução final foi criar um plugin customizado que:

### 🎯 **Estratégia Principal**
- **Mantém o papel Editor** existente
- **Adiciona capacidades específicas** do Smart Slider
- **Controla acesso via whitelist** ao invés de blacklist
- **Remove menus perigosos** explicitamente

### 🔑 **Capacidades Adicionadas**
```php
$caps = [
    'smartslider',
    'smartslider_edit',
    'smartslider_config',
    'edit_posts',
    'edit_pages',
    'publish_pages',
    'edit_theme_options', // Para menus/widgets
    'upload_files'
];
```

### 📋 **Menus Permitidos**
```php
$allowed_pages = [
    'index.php',                  // Painel
    'edit.php',                  // Posts
    'edit.php?post_type=page',   // Páginas
    'themes.php',                // Aparência
    'admin.php?page=smartslider' // Smart Slider
];
```

### 🎨 **Submenus da Aparência**
```php
$allowed_submenus = [
    'nav-menus.php',   // Menus
    'widgets.php'      // Widgets
];
```

## ✅ Benefícios

### 🔒 **Segurança**
- ✅ **Bloqueio de Customizer**: protege contra edição visual do tema por editores
- ✅ **Validação e bloqueio de páginas críticas** (`admin_init`): impede acesso a configurações, plugins, usuários etc.
- ✅ **Barra de admin limpa**: remove distrações e pontos de entrada
- ✅ **Não concede `manage_options`**: evita acesso administrativo total

### 💻 **Funcionalidade**
- ✅ **Acesso apenas ao necessário**: posts, páginas, menus, widgets e Smart Slider
- ✅ **Filtro no admin_menu limpo**: direto, com menu e submenu permitido via arrays separados
- ✅ **Smart Slider totalmente funcional**: sem limitações para editores
- ✅ **Hooks de ativação/desativação**: gerencia capacidades automaticamente

### 🛡️ **Controle Granular**
- ✅ **Whitelist approach**: mais seguro que blacklist
- ✅ **Bloqueio explícito**: páginas perigosas são bloqueadas individualmente
- ✅ **Capacidade customizada**: `smartslider_config` para controle específico
- ✅ **Limpeza de interface**: remove elementos desnecessários

## 🚀 Resultado Final

O plugin resolve completamente o problema original:
- **Editores têm acesso total ao Smart Slider** ✅
- **Segurança mantida** - sem acesso administrativo ✅
- **Interface limpa** - apenas o necessário ✅
- **Solução robusta** - sem gambiarras ✅

**⚠️ BLINDAGEM LEGAL:**

- Por questões de **confidencialidade e boas práticas**, este repositório contém **apenas arquivos de minha autoria** e não inclui dados sensíveis.
- O código-fonte pertence integralmente ao **Instituto Brasileiro de Museus (IBRAM)**.
- Este repositório é disponibilizado **exclusivamente para fins de portfólio profissional e demonstração técnica**, com a devida autorização do órgão.
- **É proibida a comercialização (venda) ou redistribuição** do tema e plugins por terceiros.