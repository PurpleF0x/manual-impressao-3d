<?php
/**
 * Homepage V2 — Manual de Impressão 3D
 * Esta página é independente da homepage de produção (index.php).
 */
require_once 'includes/functions.php';
require_once 'includes/user_notices.php';

$currentUser = isLoggedIn() ? getCurrentUser() : null;

/*
 * Índice de descoberta da V2. As rotas, títulos e temas abaixo correspondem
 * aos capítulos públicos presentes em index.php e sitemap.xml. Não é usado
 * api/search.php porque, atualmente, essa API devolve URLs simulados para o
 * Manual; mantemos assim a pesquisa da homepage ligada apenas a destinos reais.
 */
$manualSearchIndex = [
    ['title' => 'O que é a Impressão 3D?', 'url' => '/manual/o-que-e-impressao-3d', 'type' => 'Manual', 'description' => 'Conceitos fundamentais e fabricação aditiva.', 'terms' => 'impressao 3d impressão 3d conceito conceitos basico básico fabricacao fabricação aditiva camada camada a camada aprender alunos professores escola'],
    ['title' => 'Como Funciona?', 'url' => '/manual/como-funciona', 'type' => 'Manual', 'description' => 'Do modelo digital à peça final.', 'terms' => 'como funciona processo primeira impressao impressão modelo ficheiro arquivo stl 3mf slicer preparar impressora imprimir calibrar calibracao calibração'],
    ['title' => 'Tipos de Impressoras', 'url' => '/manual/tipos-de-impressoras-3d', 'type' => 'Manual', 'description' => 'Tecnologias FDM, SLA e SLS.', 'terms' => 'impressora impressoras fdm sla resina sls tecnologia tipos escolher maquina máquina'],
    ['title' => 'Iniciantes vs Profissional', 'url' => '/manual/iniciantes-vs-pro', 'type' => 'Manual', 'description' => 'Diferenças entre impressoras de entrada e profissionais.', 'terms' => 'iniciante iniciantes profissional pro impressora entrada comprar escolher comparação comparacao'],
    ['title' => 'Tipos de Filamento', 'url' => '/manual/materiais-e-filamentos', 'type' => 'Manual', 'description' => 'Materiais FDM e as suas características.', 'terms' => 'filamento filamentos material materiais pla petg abs asa tpu nylon pa cf peek temperatura bico cama'],
    ['title' => 'Matriz Técnica de Filamentos', 'url' => '/manual/comparador-de-materiais', 'type' => 'Manual', 'description' => 'Comparação técnica de materiais.', 'terms' => 'comparador comparar filamento materiais densidade resistencia resistência temperatura mesa bico tg tracao tração pla petg abs asa tpu nylon'],
    ['title' => 'Qual Filamento Usar?', 'url' => '/manual/qual-filamento-usar', 'type' => 'Manual', 'description' => 'Seleção de material por aplicação.', 'terms' => 'qual filamento escolher decorativo funcional exterior flexivel flexível escola sala aula performance pla petg asa tpu'],
    ['title' => 'Parâmetros de Impressão', 'url' => '/manual/parametros-de-impressao', 'type' => 'Manual', 'description' => 'Altura de camada, preenchimento, velocidade e temperatura.', 'terms' => 'parametros parâmetros configurar configurar calibrar calibracao calibração slicer altura camada infill preenchimento velocidade temperatura suporte suportes paredes'],
    ['title' => 'Problemas Comuns e Soluções', 'url' => '/manual/problemas-comuns-solucoes#problemas', 'type' => 'Problema', 'description' => 'Guia de diagnóstico para impressões FDM.', 'terms' => 'problema problemas solucao solução troubleshooting warping stringing layer splitting camadas under extrusion under-extrusion ghosting adesao adesão descola fios buracos ondulacoes ondulações'],
    ['title' => 'Dicas e Boas Práticas', 'url' => '/manual/dicas-e-boas-praticas', 'type' => 'Manual', 'description' => 'Primeira camada, testes, orientação e manutenção.', 'terms' => 'dicas boas praticas práticas primeira camada calibrar calibração teste orientacao orientação manutencao manutenção cama perfis'],
    ['title' => 'Software Essencial', 'url' => '/manual/software-essencial-3d', 'type' => 'Manual', 'description' => 'Slicers, CAD e repositórios de modelos.', 'terms' => 'software slicer prusaslicer orcaslicer cura tinkercad fusion 360 blender cad modelo modelos design'],
    ['title' => 'Glossário', 'url' => '/manual/glossario-termos-tecnicos', 'type' => 'Referência técnica', 'description' => 'Termos essenciais da impressão 3D.', 'terms' => 'glossario glossário termo termos gcode g-code slicer infill bed leveling retraction warping stl 3mf cad hotend hot-end pressure advance'],
    ['title' => 'Calculadora de Custos', 'url' => '/calculadora', 'type' => 'Ferramenta', 'description' => 'Estima filamento e eletricidade por impressão.', 'terms' => 'calculadora custo custos filamento eletricidade energia preco preço orçamento'],
    ['title' => 'Fórum Manual 3D', 'url' => '/forum/', 'type' => 'Fórum', 'description' => 'Perguntas, discussões e comunidades.', 'terms' => 'forum fórum comunidade pergunta perguntas discutir discussão topico tópico ajuda maker'],
    ['title' => 'IA do Manual', 'url' => '/ai', 'type' => 'IA do Manual', 'description' => 'Assistente para aprendizagem e orientação.', 'terms' => 'ia ai inteligencia inteligência assistente ajuda perguntar orientação orientacao manual'],
];
?>
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf" content="<?php echo generateCSRFToken(); ?>">
    <meta name="description" content="Aprende impressão 3D com guias técnicos, resolução de problemas, calculadora de custos, Fórum e IA do Manual.">
    <link rel="canonical" href="https://manual-3d.pt/v2">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://manual-3d.pt/v2">
    <meta property="og:title" content="Manual 3D — Guias, problemas e ferramentas de impressão 3D">
    <meta property="og:description" content="Guias técnicos, diagnóstico de problemas, calculadora de custos, Fórum e IA do Manual.">
    <meta property="og:image" content="https://manual-3d.pt/og-manual.png">
    <meta name="twitter:card" content="summary_large_image">
    <title>Manual 3D | Guias, problemas e ferramentas de impressão 3D</title>
    <link rel="icon" type="image/svg+xml" href="favicons/favicon-manual.svg">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Manual 3D — Guias, problemas e ferramentas de impressão 3D",
        "description": "Portal educativo com guias técnicos, resolução de problemas, ferramentas e comunidade sobre impressão 3D.",
        "url": "https://manual-3d.pt/v2",
        "inLanguage": "pt-PT",
        "isPartOf": { "@type": "WebSite", "name": "Manual de Impressão 3D", "url": "https://manual-3d.pt/" }
    }
    </script>
    <style>
        :root {
            --ink: #142128;
            --ink-soft: #506068;
            --paper: #f7f7f4;
            --white: #ffffff;
            --line: #dfe5e2;
            --line-dark: rgba(255,255,255,.16);
            --teal: #0e887a;
            --teal-deep: #075c55;
            --teal-pale: #e1f1ed;
            --orange: #ed6b31;
            --orange-pale: #fff0e7;
            --violet: #6253b5;
            --violet-pale: #eeebfb;
            --shadow: 0 18px 50px rgba(22, 38, 39, .09);
            --radius: 20px;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 86px; }
        body { margin: 0; min-width: 320px; background: var(--paper); color: var(--ink); font-family: Manrope, Arial, sans-serif; font-size: 16px; line-height: 1.55; }
        body.menu-open { overflow: hidden; }
        a { color: inherit; }
        button, input { font: inherit; }
        button { cursor: pointer; }
        .skip-link { position: fixed; top: 8px; left: 8px; transform: translateY(-160%); z-index: 1000; padding: 10px 14px; border-radius: 8px; background: var(--ink); color: #fff; font-weight: 700; text-decoration: none; }
        .skip-link:focus { transform: translateY(0); }
        .shell { width: min(1180px, calc(100% - 48px)); margin: 0 auto; }
        .eyebrow { margin: 0 0 12px; color: var(--teal-deep); font-family: "DM Mono", monospace; font-size: .72rem; font-weight: 500; letter-spacing: .1em; text-transform: uppercase; }
        .section-head { display: flex; align-items: end; justify-content: space-between; gap: 28px; margin-bottom: 32px; }
        .section-head h2 { max-width: 650px; margin: 0; font-size: clamp(1.8rem, 4vw, 3rem); line-height: 1.12; letter-spacing: -.045em; }
        .section-head > p { max-width: 350px; margin: 0 0 2px; color: var(--ink-soft); }
        .text-link { display: inline-flex; align-items: center; gap: 8px; color: var(--teal-deep); font-size: .9rem; font-weight: 800; text-decoration: none; }
        .text-link::after { content: "→"; transition: transform .18s ease; }
        .text-link:hover::after { transform: translateX(4px); }
        :focus-visible { outline: 3px solid var(--orange); outline-offset: 3px; }

        /* Header */
        .site-header { position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--line); background: rgba(247,247,244,.94); backdrop-filter: blur(16px); }
        .header-inner { display: flex; align-items: center; justify-content: space-between; min-height: 72px; gap: 24px; }
        .brand { display: inline-flex; align-items: center; gap: 11px; color: var(--ink); font-size: 1rem; font-weight: 800; letter-spacing: -.035em; text-decoration: none; white-space: nowrap; }
        .brand-mark { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 9px; background: var(--ink); color: #fff; font-family: "DM Mono", monospace; font-size: .72rem; letter-spacing: -.1em; }
        .primary-nav { display: flex; align-items: center; gap: 3px; }
        .primary-nav a { padding: 8px 11px; border-radius: 8px; color: #435057; font-size: .84rem; font-weight: 700; text-decoration: none; }
        .primary-nav a:hover, .primary-nav a[aria-current="true"] { background: #e9eeeb; color: var(--teal-deep); }
        .header-actions { display: flex; align-items: center; gap: 9px; }
        .header-actions a { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 8px 13px; border: 1px solid var(--line); border-radius: 9px; color: var(--ink); font-size: .8rem; font-weight: 800; text-decoration: none; white-space: nowrap; }
        .header-actions .account-link { border-color: var(--ink); background: var(--ink); color: #fff; }
        .header-actions .account-link:hover { background: #27383e; }
        .user-chip { display: inline-flex; align-items: center; gap: 7px; max-width: 170px; overflow: hidden; }
        .user-avatar { display: grid; width: 22px; height: 22px; flex: 0 0 22px; place-items: center; overflow: hidden; border-radius: 50%; background: var(--teal-pale); color: var(--teal-deep); font-size: .66rem; font-weight: 800; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .menu-button { display: none; width: 42px; height: 42px; border: 1px solid var(--line); border-radius: 9px; background: var(--white); color: var(--ink); }
        .menu-button svg { width: 21px; height: 21px; }

        /* Hero */
        .hero { position: relative; overflow: hidden; background: var(--ink); color: #fff; }
        .hero::before { content: ""; position: absolute; inset: 0; opacity: .72; background-image: linear-gradient(rgba(255,255,255,.055) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.055) 1px, transparent 1px); background-size: 34px 34px; mask-image: linear-gradient(to right, #000 0%, transparent 78%); }
        .hero::after { content: ""; position: absolute; width: 500px; height: 500px; right: -190px; bottom: -315px; border: 1px solid rgba(151,238,220,.38); border-radius: 50%; box-shadow: 0 0 0 62px rgba(151,238,220,.05), 0 0 0 124px rgba(151,238,220,.025); }
        .hero-inner { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(0, 1.04fr) minmax(330px, .96fr); align-items: center; min-height: 620px; gap: 34px; }
        .hero-copy { padding: 76px 0; }
        .hero .eyebrow { color: #a6e8dc; }
        .hero h1 { max-width: 710px; margin: 0; font-size: clamp(2.7rem, 5.8vw, 5.15rem); line-height: .99; letter-spacing: -.07em; }
        .hero h1 em { color: #a6e8dc; font-style: normal; }
        .hero-intro { max-width: 590px; margin: 24px 0 0; color: #d2dbd8; font-size: clamp(1rem, 1.6vw, 1.16rem); }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 31px; }
        .button { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 48px; padding: 12px 17px; border: 1px solid transparent; border-radius: 9px; font-size: .87rem; font-weight: 800; text-decoration: none; transition: transform .18s ease, background .18s ease, border-color .18s ease; }
        .button:hover { transform: translateY(-2px); }
        .button.primary { background: #b7eee4; color: #0c3733; }
        .button.secondary { border-color: rgba(255,255,255,.28); color: #fff; }
        .button.secondary:hover { border-color: #fff; background: rgba(255,255,255,.08); }
        .hero-search-wrap { position: relative; width: min(100%, 555px); margin-top: 42px; }
        .quick-search { display: flex; align-items: center; width: 100%; min-height: 58px; border: 1px solid rgba(255,255,255,.2); border-radius: 13px; background: rgba(255,255,255,.08); transition: background .18s ease, border-color .18s ease; }
        .quick-search:focus-within { border-color: #a6e8dc; background: rgba(255,255,255,.13); }
        .quick-search svg { width: 21px; height: 21px; flex: 0 0 auto; margin: 0 12px 0 17px; color: #a6e8dc; }
        .quick-search input { width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; color: #fff; font-size: .9rem; }
        .quick-search input::placeholder { color: #c2ceca; }
        .search-key { margin-right: 12px; padding: 3px 7px; border: 1px solid rgba(255,255,255,.2); border-radius: 5px; color: #b8c7c2; font-family: "DM Mono", monospace; font-size: .64rem; white-space: nowrap; }
        .hero-search-results { position: absolute; z-index: 5; top: calc(100% + 6px); left: 0; width: 100%; max-height: min(58vh, 460px); overflow: auto; border: 1px solid #cdd8d3; border-radius: 13px; background: #fff; box-shadow: var(--shadow); }
        .hero-search-results:empty { display: none; }
        .search-result { display: block; padding: 11px 16px; border-bottom: 1px solid #edf0ee; color: var(--ink); text-decoration: none; }
        .search-result:last-child { border-bottom: 0; }
        .search-result:hover { background: var(--teal-pale); }
        .search-result small { display: block; margin-bottom: 2px; color: var(--teal-deep); font-family: "DM Mono", monospace; font-size: .62rem; letter-spacing: .07em; text-transform: uppercase; }
        .search-result strong { font-size: .82rem; }
        .search-result-description { display: block; margin-top: 2px; color: var(--ink-soft); font-size: .72rem; }
        .search-empty { padding: 14px 16px 8px; color: var(--ink-soft); font-size: .84rem; }
        .search-next-step { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 8px; padding: 10px 12px; border-radius: 8px; background: #f1f6f3; color: #365d57; font-size: .72rem; }
        .search-next-step-links { display: flex; flex: 0 0 auto; gap: 8px; }
        .search-next-step a { color: var(--teal-deep); font-size: .72rem; font-weight: 800; text-decoration: none; }
        .search-next-step a:hover { text-decoration: underline; }
        .printer-visual { position: relative; align-self: end; width: min(100%, 460px); justify-self: end; padding-bottom: 42px; }
        .printer-caption { position: absolute; z-index: 2; left: -26px; top: 75px; padding: 9px 11px; border: 1px solid rgba(255,255,255,.18); border-radius: 8px; background: rgba(16,33,40,.8); color: #d8e5e2; font-family: "DM Mono", monospace; font-size: .65rem; backdrop-filter: blur(8px); }
        .printer-visual svg { display: block; width: 100%; height: auto; overflow: visible; filter: drop-shadow(18px 18px 0 rgba(0,0,0,.15)); }
        .hero-footnote { display: flex; align-items: center; gap: 9px; margin-top: 20px; color: #aebeba; font-size: .75rem; }
        .hero-footnote span { width: 7px; height: 7px; border-radius: 50%; background: #a6e8dc; box-shadow: 0 0 0 4px rgba(166,232,220,.13); }

        /* Main paths */
        .path-section { padding: 94px 0 76px; }
        .paths { display: grid; grid-template-columns: repeat(12, 1fr); gap: 14px; }
        .path { position: relative; display: flex; min-height: 238px; flex-direction: column; justify-content: space-between; padding: 23px; overflow: hidden; border: 1px solid var(--line); border-radius: var(--radius); background: var(--white); text-decoration: none; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
        .path:hover { z-index: 1; transform: translateY(-4px); border-color: #bfcec8; box-shadow: var(--shadow); }
        .path.learn { grid-column: span 5; background: #eef6f2; }
        .path.solve { grid-column: span 4; background: #fff4ed; }
        .path.tools { grid-column: span 3; background: #f2f0fb; }
        .path.community { grid-column: span 7; min-height: 195px; background: #fff; }
        .path.ai { grid-column: span 5; min-height: 195px; background: var(--ink); color: #fff; }
        .path-icon { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 12px; background: rgba(255,255,255,.76); color: var(--teal-deep); }
        .path.solve .path-icon { color: #b34b1e; }
        .path.tools .path-icon { color: var(--violet); }
        .path.ai .path-icon { background: rgba(166,232,220,.14); color: #a6e8dc; }
        .path-icon svg { width: 23px; height: 23px; }
        .path h3 { margin: 27px 0 5px; font-size: 1.18rem; letter-spacing: -.035em; }
        .path p { max-width: 390px; margin: 0; color: var(--ink-soft); font-size: .85rem; }
        .path.ai p { color: #c5d3d0; }
        .path-arrow { align-self: end; margin-top: 18px; font-size: 1.1rem; font-weight: 800; }
        .path.ai::after { content: ""; position: absolute; right: -50px; bottom: -82px; width: 220px; height: 220px; border: 1px solid rgba(166,232,220,.3); border-radius: 50%; box-shadow: 0 0 0 26px rgba(166,232,220,.04); }

        /* Start */
        .start-section { padding: 84px 0; border-top: 1px solid var(--line); background: #fff; }
        .journey { display: grid; grid-template-columns: minmax(240px, .72fr) minmax(0, 1.28fr); gap: clamp(28px, 6vw, 80px); align-items: start; }
        .journey-intro { position: sticky; top: 98px; }
        .journey-intro h2 { margin: 0; font-size: clamp(1.9rem, 3.4vw, 3rem); line-height: 1.1; letter-spacing: -.05em; }
        .journey-intro p { color: var(--ink-soft); }
        .learning-note { display: flex; align-items: flex-start; gap: 10px; margin: 25px 0 0; padding: 14px; border-left: 3px solid var(--teal); background: #eff6f2; color: #365d57; font-size: .78rem; }
        .learning-note svg { width: 18px; height: 18px; flex: 0 0 auto; margin-top: 2px; color: var(--teal-deep); }
        .journey-list { margin: 0; padding: 0; list-style: none; border-top: 1px solid var(--line); }
        .journey-list li { display: grid; grid-template-columns: 46px 1fr auto; align-items: center; gap: 16px; min-height: 86px; border-bottom: 1px solid var(--line); }
        .journey-number { color: #91a19b; font-family: "DM Mono", monospace; font-size: .74rem; }
        .journey-list a { color: var(--ink); font-size: .96rem; font-weight: 800; text-decoration: none; }
        .journey-list a small { display: block; margin-top: 2px; color: var(--ink-soft); font-size: .77rem; font-weight: 500; }
        .journey-list a:hover { color: var(--teal-deep); }
        .journey-arrow { color: var(--teal); font-size: 1.1rem; }

        /* Knowledge */
        .knowledge-section { padding: 94px 0; }
        .guide-layout { display: grid; grid-template-columns: 1.28fr .72fr; gap: 16px; }
        .guide-main, .guide-aside { border: 1px solid var(--line); border-radius: var(--radius); background: var(--white); }
        .guide-main { padding: clamp(22px, 4vw, 40px); }
        .guide-main > p { max-width: 590px; margin: 0 0 25px; color: var(--ink-soft); }
        .guide-groups { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0 30px; }
        .guide-group { padding: 18px 0; border-top: 1px solid var(--line); }
        .guide-group h3 { margin: 0 0 10px; font-size: .86rem; }
        .guide-group a { display: block; margin: 7px 0; color: var(--ink-soft); font-size: .82rem; text-decoration: none; }
        .guide-group a:hover { color: var(--teal-deep); text-decoration: underline; }
        .guide-aside { display: flex; flex-direction: column; justify-content: space-between; padding: clamp(22px, 3vw, 34px); background: var(--teal-deep); color: #fff; }
        .guide-aside .eyebrow { color: #a6e8dc; }
        .guide-aside h3 { margin: 0; font-size: clamp(1.45rem, 2.5vw, 2rem); line-height: 1.12; letter-spacing: -.045em; }
        .guide-aside p { color: #c6dcd7; font-size: .88rem; }
        .guide-aside .text-link { color: #d6faf3; }

        /* Troubleshooting */
        .troubleshooting-section { padding: 94px 0; background: #f0f4f1; }
        .problem-grid { display: grid; grid-template-columns: repeat(5, 1fr); border-top: 1px solid #cfd9d4; border-left: 1px solid #cfd9d4; }
        .problem { display: flex; min-height: 194px; flex-direction: column; justify-content: space-between; padding: 19px; border-right: 1px solid #cfd9d4; border-bottom: 1px solid #cfd9d4; background: rgba(255,255,255,.55); color: var(--ink); text-decoration: none; transition: background .18s ease; }
        .problem:hover { background: #fff; }
        .problem .problem-mark { color: var(--orange); font-family: "DM Mono", monospace; font-size: .68rem; letter-spacing: .07em; text-transform: uppercase; }
        .problem h3 { margin: 18px 0 5px; font-size: 1.02rem; letter-spacing: -.03em; }
        .problem p { margin: 0; color: var(--ink-soft); font-size: .75rem; }
        .problem-action { display: block; margin-top: 16px; color: var(--teal-deep); font-size: .82rem; font-weight: 800; }
        .trouble-footer { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-top: 24px; padding: 22px 0 0; }
        .trouble-footer p { margin: 0; color: var(--ink-soft); font-size: .86rem; }

        /* Tools */
        .tools-section { padding: 94px 0; }
        .tool-callout { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 26px; padding: clamp(24px, 4vw, 44px); border: 1px solid #cdded9; border-radius: var(--radius); background: var(--teal-pale); }
        .tool-symbol { display: grid; width: 65px; height: 65px; place-items: center; border-radius: 15px; background: var(--teal-deep); color: #d6faf3; }
        .tool-symbol svg { width: 34px; height: 34px; }
        .tool-callout h3 { margin: 0 0 5px; font-size: 1.35rem; letter-spacing: -.04em; }
        .tool-callout p { max-width: 600px; margin: 0; color: #365d57; font-size: .88rem; }
        .tool-callout .button { background: var(--teal-deep); color: #fff; white-space: nowrap; }
        .tool-callout .button:hover { background: #074842; }

        /* Community & AI */
        .community-section { padding: 90px 0; background: var(--ink); color: #fff; }
        .community-section .eyebrow { color: #a6e8dc; }
        .community-section .section-head h2 { color: #fff; }
        .community-section .section-head > p { color: #bdcbc7; }
        .community-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 16px; }
        .community-card { padding: clamp(25px, 4vw, 44px); border: 1px solid var(--line-dark); border-radius: var(--radius); background: rgba(255,255,255,.035); }
        .community-card h3 { margin: 0; font-size: clamp(1.5rem, 3vw, 2rem); line-height: 1.14; letter-spacing: -.05em; }
        .community-card > p { max-width: 580px; color: #bfcec9; font-size: .88rem; }
        .community-points { display: grid; grid-template-columns: repeat(2, 1fr); gap: 9px; margin: 26px 0; padding: 0; list-style: none; }
        .community-points li { padding: 11px; border: 1px solid rgba(255,255,255,.1); border-radius: 9px; color: #dbe6e2; font-size: .78rem; }
        .community-card .button { background: #fff; color: var(--ink); }
        .community-card .button:hover { background: #dff5ef; }
        .forum-ai-note { margin-top: 22px; color: #aebfba; font-size: .74rem; }
        .manual-ai-card { position: relative; overflow: hidden; background: #123f3d; }
        .manual-ai-card::before { content: ""; position: absolute; width: 270px; height: 270px; right: -115px; top: -105px; border: 1px solid rgba(166,232,220,.32); border-radius: 50%; box-shadow: 0 0 0 33px rgba(166,232,220,.045), 0 0 0 66px rgba(166,232,220,.025); }
        .manual-ai-card > * { position: relative; z-index: 1; }
        .manual-ai-icon { display: grid; width: 48px; height: 48px; place-items: center; border-radius: 13px; background: #a6e8dc; color: #0b3934; }
        .manual-ai-icon svg { width: 27px; height: 27px; }
        .manual-ai-card h3 { margin-top: 23px; }
        .manual-ai-card .button { background: #a6e8dc; color: #0b3934; }
        .manual-ai-card .button:hover { background: #d8faf4; }

        /* Footer */
        .site-footer { padding: 58px 0 35px; background: #0d1b20; color: #d7e0dd; }
        .footer-grid { display: grid; grid-template-columns: 1.25fr repeat(3, .65fr); gap: 28px; }
        .footer-brand { color: #fff; }
        .footer-brand .brand-mark { background: #d7f5ef; color: #0e3834; }
        .footer-brand p { max-width: 290px; color: #9aaca7; font-size: .78rem; }
        .footer-col h2 { margin: 0 0 13px; color: #a6e8dc; font-family: "DM Mono", monospace; font-size: .66rem; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; }
        .footer-col a { display: block; margin: 8px 0; color: #d7e0dd; font-size: .78rem; text-decoration: none; }
        .footer-col a:hover { color: #a6e8dc; }
        .footer-bottom { display: flex; justify-content: space-between; gap: 16px; margin-top: 48px; padding-top: 21px; border-top: 1px solid rgba(255,255,255,.12); color: #91a49e; font-family: "DM Mono", monospace; font-size: .66rem; }
        .footer-social { display: flex; gap: 14px; }
        .footer-social a { color: #d7e0dd; text-decoration: none; }

        @media (max-width: 920px) {
            .primary-nav { display: none; }
            .menu-button { display: grid; place-items: center; }
            .primary-nav.open { position: fixed; z-index: 99; inset: 73px 0 auto; display: flex; flex-direction: column; align-items: stretch; gap: 2px; padding: 18px max(24px, calc((100vw - 1180px)/2)); border-bottom: 1px solid var(--line); background: #f7f7f4; box-shadow: 0 16px 30px rgba(16,32,37,.09); }
            .primary-nav.open a { padding: 11px 0; border-radius: 0; font-size: .93rem; }
            .header-actions .forum-link { display: none; }
            .hero-inner { grid-template-columns: 1fr; min-height: auto; }
            .hero-copy { padding: 77px 0 16px; }
            .printer-visual { width: min(100%, 470px); margin: 0 auto; justify-self: center; padding-bottom: 20px; }
            .printer-caption { left: 0; top: 34px; }
            .path.learn { grid-column: span 6; }
            .path.solve { grid-column: span 6; }
            .path.tools { grid-column: span 4; }
            .path.community { grid-column: span 8; }
            .path.ai { grid-column: span 4; }
            .guide-layout, .community-grid { grid-template-columns: 1fr; }
            .guide-aside { min-height: 260px; }
            .problem-grid { grid-template-columns: repeat(3, 1fr); }
            .footer-grid { grid-template-columns: 1.2fr repeat(3, .8fr); }
        }
        @media (max-width: 640px) {
            .shell { width: min(100% - 32px, 1180px); }
            .header-inner { min-height: 64px; gap: 10px; }
            .brand { font-size: .92rem; }
            .brand-mark { width: 31px; height: 31px; }
            .header-actions .login-link { display: none; }
            .header-actions a { min-height: 36px; padding: 7px 10px; font-size: .74rem; }
            .header-actions .user-chip { max-width: 110px; }
            .menu-button { width: 38px; height: 38px; }
            .primary-nav.open { top: 65px; }
            .section-head { display: block; margin-bottom: 25px; }
            .section-head > p { margin-top: 12px; }
            .hero-copy { padding: 58px 0 11px; }
            .hero h1 { font-size: clamp(2.45rem, 13vw, 3.4rem); }
            .hero-intro { margin-top: 19px; font-size: .96rem; }
            .hero-actions { margin-top: 25px; }
            .button { width: 100%; min-height: 47px; }
            .hero-search-wrap { margin-top: 29px; }
            .search-key { display: none; }
            .hero-search-results { width: 100%; }
            .printer-visual { margin-left: -4px; width: calc(100% + 8px); }
            .printer-caption { font-size: .58rem; }
            .path-section, .knowledge-section, .tools-section, .troubleshooting-section { padding: 64px 0; }
            .start-section, .community-section { padding: 64px 0; }
            .paths { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .path, .path.learn, .path.solve, .path.tools, .path.community, .path.ai { grid-column: span 1; min-height: 198px; padding: 17px; }
            .path.community, .path.ai { min-height: 190px; }
            .path h3 { margin-top: 20px; font-size: 1rem; }
            .path p { font-size: .74rem; }
            .path-icon { width: 37px; height: 37px; }
            .journey { grid-template-columns: 1fr; gap: 28px; }
            .journey-intro { position: static; }
            .journey-list li { grid-template-columns: 32px 1fr auto; gap: 8px; min-height: 78px; }
            .guide-groups { grid-template-columns: 1fr; }
            .problem-grid { grid-template-columns: 1fr 1fr; }
            .problem { min-height: 180px; padding: 15px; }
            .problem h3 { margin-top: 12px; font-size: .93rem; }
            .problem p { font-size: .7rem; }
            .trouble-footer { display: block; }
            .trouble-footer .text-link { margin-top: 14px; }
            .tool-callout { grid-template-columns: 1fr; gap: 17px; }
            .tool-symbol { width: 57px; height: 57px; }
            .community-points { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 27px 16px; }
            .footer-brand { grid-column: 1 / -1; }
            .footer-bottom { flex-direction: column; margin-top: 35px; }
        }
        @media (max-width: 390px) { .header-actions .account-link { display: none; } .paths { grid-template-columns: 1fr; } .path, .path.learn, .path.solve, .path.tools, .path.community, .path.ai { grid-column: 1; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; } }
    </style>
</head>
<body>
    <a class="skip-link" href="#conteudo">Saltar para o conteúdo</a>
    <?php renderUserNotice(); ?>

    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="/v2" aria-label="Manual 3D, início">
                <span class="brand-mark" aria-hidden="true">3D</span><span>Manual 3D</span>
            </a>
            <nav class="primary-nav" id="main-nav" aria-label="Navegação principal">
                <a href="#aprender">Aprender</a>
                <a href="#resolver">Resolver problemas</a>
                <a href="#ferramentas">Ferramentas</a>
                <a href="#comunidade">Comunidade</a>
                <a href="/ai">IA do Manual</a>
            </nav>
            <div class="header-actions">
                <a class="forum-link" href="/forum/">Fórum</a>
                <?php if ($currentUser): ?>
                    <a class="account-link user-chip" href="/perfil" title="Abrir perfil">
                        <span class="user-avatar">
                            <?php if (!empty($currentUser['avatar_url'])): ?><img src="<?php echo sanitize(avPath($currentUser['avatar_url'])); ?>" alt=""><?php else: ?><?php echo sanitize($currentUser['avatar'] ?? generateAvatar($currentUser['full_name'] ?? '')); ?><?php endif; ?>
                        </span>
                        <span><?php echo sanitize($currentUser['full_name'] ?? 'Perfil'); ?></span>
                    </a>
                <?php else: ?>
                    <a class="login-link" href="/login">Entrar</a>
                    <a class="account-link" href="/register">Criar conta</a>
                <?php endif; ?>
                <button class="menu-button" type="button" id="menu-button" aria-label="Abrir menu" aria-controls="main-nav" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
    </header>

    <main id="conteudo">
        <section class="hero" aria-labelledby="hero-title">
            <div class="shell hero-inner">
                <div class="hero-copy">
                    <p class="eyebrow">Manual de impressão 3D</p>
                    <h1 id="hero-title">Aprende a imprimir. <em>Resolve melhor.</em></h1>
                    <p class="hero-intro">Um espaço para perceber impressão 3D, preparar cada impressão, diagnosticar falhas e trocar conhecimento com outros makers.</p>
                    <div class="hero-actions">
                        <a class="button primary" href="#começar">Começar pelo essencial <span aria-hidden="true">→</span></a>
                        <a class="button secondary" href="#resolver">Tenho um problema</a>
                    </div>
                    <div class="hero-search-wrap">
                        <div class="quick-search" role="search">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                            <label class="sr-only" for="manual-search">Pesquisar no Manual e no Fórum</label>
                            <input id="manual-search" type="search" role="combobox" autocomplete="off" placeholder="Procura um tema, material ou problema…" aria-controls="search-results" aria-expanded="false" aria-autocomplete="list" aria-haspopup="listbox">
                            <span class="search-key" aria-hidden="true">Ctrl K</span>
                        </div>
                        <div class="hero-search-results" id="search-results" role="listbox" aria-label="Resultados da pesquisa" aria-live="polite"></div>
                    </div>
                    <div class="hero-footnote"><span aria-hidden="true"></span> Guias técnicos, ferramentas e comunidade no mesmo lugar.</div>
                </div>
                <div class="printer-visual" aria-hidden="true">
                    <span class="printer-caption">FDM · camada a camada</span>
                    <svg viewBox="0 0 480 410" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M92 347H388" stroke="#9EC5BC" stroke-width="4" stroke-linecap="round"/>
                        <path d="M117 347V98H370V347" stroke="#DDF9F2" stroke-width="13" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M117 98H370" stroke="#76C7B7" stroke-width="13" stroke-linecap="round"/>
                        <path d="M154 98V183M332 98V183" stroke="#76C7B7" stroke-width="7"/>
                        <rect x="142" y="176" width="217" height="17" rx="4" fill="#DDF9F2"/>
                        <rect x="205" y="166" width="72" height="29" rx="7" fill="#82D9C9"/>
                        <path d="M223 193H259L253 231H229L223 193Z" fill="#F18A57"/>
                        <path d="M240 232V250" stroke="#F7B494" stroke-width="7" stroke-linecap="round"/>
                        <path d="M238 250L242 257L246 250" fill="#F7B494"/>
                        <path d="M149 291H341" stroke="#9EC5BC" stroke-width="11" stroke-linecap="round"/>
                        <path d="M161 298H328L305 327H184L161 298Z" fill="#5B9C92"/>
                        <path d="M188 286C188 273 199 264 212 264H271C285 264 296 273 296 286V291H188V286Z" fill="#A6E8DC"/>
                        <path d="M204 277H280" stroke="#DDF9F2" stroke-width="3" stroke-linecap="round" stroke-dasharray="4 6"/>
                        <rect x="130" y="117" width="36" height="36" rx="6" fill="#17363B" stroke="#76C7B7" stroke-width="3"/>
                        <circle cx="148" cy="135" r="7" fill="#A6E8DC"/>
                        <path d="M99 353H388" stroke="#76C7B7" stroke-width="10" stroke-linecap="round"/>
                        <path d="M95 358C105 370 118 376 133 376H351C366 376 378 370 388 358" stroke="#DDF9F2" stroke-width="7" stroke-linecap="round"/>
                        <path d="M89 389H393" stroke="#345E5D" stroke-width="8" stroke-linecap="round"/>
                        <circle cx="159" cy="389" r="8" fill="#A6E8DC"/><circle cx="323" cy="389" r="8" fill="#A6E8DC"/>
                        <path d="M89 68L106 51M374 63L392 45M404 161L431 154" stroke="#76C7B7" stroke-width="3" stroke-linecap="round"/>
                        <circle cx="86" cy="64" r="4" fill="#F18A57"/><circle cx="403" cy="59" r="5" fill="#A6E8DC"/><circle cx="436" cy="152" r="3" fill="#A6E8DC"/>
                    </svg>
                </div>
            </div>
        </section>

        <section class="path-section" id="aprender" aria-labelledby="paths-title">
            <div class="shell">
                <div class="section-head">
                    <div><p class="eyebrow">Por onde queres seguir?</p><h2 id="paths-title">Cinco caminhos para usar o Manual</h2></div>
                    <p>Escolhe o ponto de entrada que faz sentido para o que estás a fazer agora.</p>
                </div>
                <div class="paths">
                    <a class="path learn" href="#começar"><span class="path-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z"/><path d="M4 19h16"/></svg></span><span><h3>Aprender</h3><p>Guias organizados desde os conceitos fundamentais até aos materiais e parâmetros de impressão.</p></span><span class="path-arrow">→</span></a>
                    <a class="path solve" href="#resolver"><span class="path-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m14.7 6.3 3 3M3 21l6.1-6.1a5.1 5.1 0 0 0 6.1-7.8l-3.1 3.1-3.2-3.2L12 4a5.1 5.1 0 0 0-7.8 6.1L3 11.3"/><path d="m14 14 7 7"/></svg></span><span><h3>Resolver problemas</h3><p>Identifica sintomas de impressão e avança para o capítulo de troubleshooting.</p></span><span class="path-arrow">→</span></a>
                    <a class="path tools" href="#ferramentas"><span class="path-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h2m4 0h2M8 15h2m4 0h2M8 18h8"/></svg></span><span><h3>Ferramentas</h3><p>Calcula o custo de uma impressão.</p></span><span class="path-arrow">→</span></a>
                    <a class="path community" href="#comunidade"><span class="path-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 11.5a4 4 0 0 1-4 4H9l-5 4v-8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4Z"/><path d="M8 11.5h.01M12 11.5h.01M16 11.5h.01" stroke-linecap="round" stroke-width="2.5"/></svg></span><span><h3>Comunidade</h3><p>Faz perguntas, partilha experiências e encontra conversas no Fórum.</p></span><span class="path-arrow">→</span></a>
                    <a class="path ai" href="/ai"><span class="path-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="6" width="14" height="12" rx="3"/><path d="M12 3v3M9 11h.01M15 11h.01M9 15h6" stroke-linecap="round"/><path d="M3 10v4M21 10v4"/></svg></span><span><h3>IA do Manual</h3><p>Uma ajuda complementar para encontrares informação e orientares o diagnóstico.</p></span><span class="path-arrow">→</span></a>
                </div>
            </div>
        </section>

        <section class="start-section" id="começar" aria-labelledby="start-title">
            <div class="shell journey">
                <div class="journey-intro"><p class="eyebrow">Estou a começar</p><h2 id="start-title">Um percurso simples para a primeira impressão.</h2><p>Os capítulos existentes do Manual, dispostos numa sequência prática para começares com contexto.</p><a class="text-link" href="/manual/o-que-e-impressao-3d">Abrir o primeiro capítulo</a><div class="learning-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z"/><path d="M4 19h16"/></svg><span><strong>Para aprender passo a passo.</strong><br>Útil para estudo individual, trabalhos e preparação de aulas: segue os capítulos pela ordem apresentada.</span></div></div>
                <ol class="journey-list">
                    <li><span class="journey-number">01</span><a href="/manual/o-que-e-impressao-3d">O que é a Impressão 3D?<small>Conceitos fundamentais para começar</small></a><span class="journey-arrow">→</span></li>
                    <li><span class="journey-number">02</span><a href="/manual/tipos-de-impressoras-3d">Tipos de Impressoras<small>Conhece FDM, SLA e SLS</small></a><span class="journey-arrow">→</span></li>
                    <li><span class="journey-number">03</span><a href="/manual/materiais-e-filamentos">Tipos de Filamento<small>Começa pelos materiais e pelas suas características</small></a><span class="journey-arrow">→</span></li>
                    <li><span class="journey-number">04</span><a href="/manual/software-essencial-3d">Software Essencial<small>Slicers e ferramentas para preparar o ficheiro</small></a><span class="journey-arrow">→</span></li>
                    <li><span class="journey-number">05</span><a href="/manual/como-funciona">Como Funciona?<small>Do modelo digital até à peça final</small></a><span class="journey-arrow">→</span></li>
                    <li><span class="journey-number">06</span><a href="/manual/parametros-de-impressao">Parâmetros de Impressão<small>Altura de camada, preenchimento e outros ajustes</small></a><span class="journey-arrow">→</span></li>
                    <li><span class="journey-number">07</span><a href="/manual/problemas-comuns-solucoes">Problemas Comuns e Soluções<small>O próximo passo quando algo não corre como esperado</small></a><span class="journey-arrow">→</span></li>
                </ol>
            </div>
        </section>

        <section class="knowledge-section" aria-labelledby="guides-title">
            <div class="shell">
                <div class="section-head"><div><p class="eyebrow">Conhecimento técnico</p><h2 id="guides-title">Uma biblioteca para consultar enquanto crias.</h2></div><p>O Manual reúne temas fundamentais, materiais, prática e referência técnica em capítulos acessíveis.</p></div>
                <div class="guide-layout">
                    <article class="guide-main">
                        <p>Explora o conteúdo por área. Cada ligação abre o respetivo capítulo do Manual atual.</p>
                        <div class="guide-groups">
                            <div class="guide-group"><h3>Fundamentos</h3><a href="/manual/o-que-e-impressao-3d">O que é a Impressão 3D?</a><a href="/manual/como-funciona">Como Funciona?</a><a href="/manual/tipos-de-impressoras-3d">Tipos de Impressoras</a><a href="/manual/iniciantes-vs-pro">Iniciantes vs Profissional</a></div>
                            <div class="guide-group"><h3>Materiais</h3><a href="/manual/materiais-e-filamentos">Tipos de Filamento</a><a href="/manual/comparador-de-materiais">Matriz Técnica de Filamentos</a><a href="/manual/qual-filamento-usar">Qual Filamento Usar?</a></div>
                            <div class="guide-group"><h3>Preparação e prática</h3><a href="/manual/parametros-de-impressao">Parâmetros de Impressão</a><a href="/manual/dicas-e-boas-praticas">Dicas e Boas Práticas</a><a href="/manual/software-essencial-3d">Software Essencial</a></div>
                            <div class="guide-group"><h3>Referência</h3><a href="/manual/glossario-termos-tecnicos">Glossário</a><a href="/manual/problemas-comuns-solucoes">Problemas Comuns e Soluções</a><a href="/manual/ferramentas-de-calculo">Ferramentas de Cálculo</a></div>
                        </div>
                    </article>
                    <aside class="guide-aside"><div><p class="eyebrow">Consulta rápida</p><h3>Não sabes o que significa um termo?</h3><p>O glossário reúne vocabulário essencial como G-code, slicer, infill, bed leveling, retraction e hot-end.</p></div><a class="text-link" href="/manual/glossario-termos-tecnicos">Abrir glossário</a></aside>
                </div>
            </div>
        </section>

        <section class="troubleshooting-section" id="resolver" aria-labelledby="troubleshoot-title">
            <div class="shell">
                <div class="section-head"><div><p class="eyebrow">Diagnóstico</p><h2 id="troubleshoot-title">Alguma coisa não está a correr bem?</h2></div><p>Reconhece o sintoma e abre o guia real de problemas comuns do Manual.</p></div>
                <div class="problem-grid">
                    <a class="problem" href="/manual/problemas-comuns-solucoes#problemas"><span class="problem-mark">Peça descola</span><span><h3>Warping</h3><p>As bordas levantam da cama durante a impressão.</p></span><span class="problem-action">Abrir guia de problemas →</span></a>
                    <a class="problem" href="/manual/problemas-comuns-solucoes#problemas"><span class="problem-mark">Fios entre peças</span><span><h3>Stringing</h3><p>Fios finos ligam partes que deviam ficar separadas.</p></span><span class="problem-action">Abrir guia de problemas →</span></a>
                    <a class="problem" href="/manual/problemas-comuns-solucoes#problemas"><span class="problem-mark">Camadas separam</span><span><h3>Layer Splitting</h3><p>As camadas não estão a aderir umas às outras.</p></span><span class="problem-action">Abrir guia de problemas →</span></a>
                    <a class="problem" href="/manual/problemas-comuns-solucoes#problemas"><span class="problem-mark">Falta material</span><span><h3>Under-extrusion</h3><p>Há buracos ou paredes fracas na peça.</p></span><span class="problem-action">Abrir guia de problemas →</span></a>
                    <a class="problem" href="/manual/problemas-comuns-solucoes#problemas"><span class="problem-mark">Ondulações</span><span><h3>Ghosting</h3><p>Ondas ou ecos aparecem nas paredes da impressão.</p></span><span class="problem-action">Abrir guia de problemas →</span></a>
                </div>
                <div class="trouble-footer"><p>O Manual reúne estes sintomas num único guia; não existem artigos individuais para cada um. O capítulo inclui ainda indicações de segurança.</p><a class="text-link" href="/forum/comunidade?slug=troubleshooting">Discutir um problema no Fórum</a></div>
            </div>
        </section>

        <section class="tools-section" id="ferramentas" aria-labelledby="tools-title">
            <div class="shell"><div class="section-head"><div><p class="eyebrow">Ferramentas práticas</p><h2 id="tools-title">O Manual também ajuda a fazer contas.</h2></div><p>Usa a ferramenta já disponível para estimar custos antes de iniciares uma impressão.</p></div>
                <article class="tool-callout"><div class="tool-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 11h2m4 0h2M8 15h2m4 0h2M8 18h8"/></svg></div><div><h3>Calculadora de custos de impressão</h3><p>Estima o gasto de filamento e eletricidade de cada peça com a calculadora do Manual 3D.</p></div><a class="button" href="/calculadora">Abrir calculadora <span aria-hidden="true">→</span></a></article>
            </div>
        </section>

        <section class="community-section" id="comunidade" aria-labelledby="community-title">
            <div class="shell">
                <div class="section-head"><div><p class="eyebrow">Comunidade e assistência</p><h2 id="community-title">Aprende também com outros makers.</h2></div><p>O Fórum e a IA do Manual são espaços diferentes, com funções complementares.</p></div>
                <div class="community-grid">
                    <article class="community-card"><h3>Leva a questão para a comunidade.</h3><p>No Fórum podes iniciar uma discussão, responder a outros utilizadores, partilhar experiências e acompanhar conversas sobre impressão 3D.</p><ul class="community-points"><li>Fazer uma pergunta</li><li>Responder a uma discussão</li><li>Partilhar uma experiência</li><li>Explorar comunidades</li></ul><a class="button" href="/forum/">Abrir o Fórum <span aria-hidden="true">→</span></a><p class="forum-ai-note"><strong>IA do Fórum:</strong> o assistente disponível no próprio Fórum. É uma experiência independente da IA do Manual.</p></article>
                    <article class="community-card manual-ai-card"><div class="manual-ai-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="6" width="14" height="12" rx="3"/><path d="M12 3v3M9 11h.01M15 11h.01M9 15h6" stroke-linecap="round"/><path d="M3 10v4M21 10v4"/></svg></div><h3>A IA complementa o Manual.</h3><p>O Manual contém o conhecimento. A IA do Manual ajuda a encontrar informação e a orientar o diagnóstico, sem substituir os guias técnicos.</p><a class="button" href="/ai">Abrir a IA do Manual <span aria-hidden="true">→</span></a></article>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="shell">
            <div class="footer-grid">
                <div class="footer-brand"><a class="brand" href="/v2"><span class="brand-mark" aria-hidden="true">3D</span><span>Manual 3D</span></a><p>Um centro para aprender impressão 3D, resolver problemas, utilizar ferramentas e partilhar conhecimento.</p></div>
                <div class="footer-col"><h2>Manual</h2><a href="/manual/o-que-e-impressao-3d">Começar a aprender</a><a href="/manual/problemas-comuns-solucoes">Resolver problemas</a><a href="/manual/glossario-termos-tecnicos">Glossário</a></div>
                <div class="footer-col"><h2>Explorar</h2><a href="/calculadora">Calculadora</a><a href="/forum/">Fórum</a><a href="/ai">IA do Manual</a></div>
                <div class="footer-col"><h2>Informação</h2><a href="/sobre">Sobre</a><a href="/contacto">Contacto</a><a href="/suporte">Suporte</a><a href="/terms">Termos</a><a href="/privacy">Privacidade</a></div>
            </div>
            <div class="footer-bottom"><span>© <?php echo date('Y'); ?> Manual de Impressão 3D</span><span class="footer-social"><a href="https://github.com/PurpleF0x" target="_blank" rel="noopener">GitHub</a><a href="https://www.linkedin.com/in/martim-s%C3%A1-2719351ba/" target="_blank" rel="noopener">LinkedIn</a></span></div>
        </div>
    </footer>

    <style>.sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }</style>
    <script>
        (() => {
            const menuButton = document.getElementById('menu-button');
            const nav = document.getElementById('main-nav');
            const closeMenu = () => { nav.classList.remove('open'); document.body.classList.remove('menu-open'); menuButton.setAttribute('aria-expanded', 'false'); menuButton.setAttribute('aria-label', 'Abrir menu'); };
            menuButton.addEventListener('click', () => {
                const opening = !nav.classList.contains('open');
                nav.classList.toggle('open', opening); document.body.classList.toggle('menu-open', opening);
                menuButton.setAttribute('aria-expanded', String(opening)); menuButton.setAttribute('aria-label', opening ? 'Fechar menu' : 'Abrir menu');
            });
            nav.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
            window.addEventListener('resize', () => { if (window.innerWidth > 920) closeMenu(); });

            const searchInput = document.getElementById('manual-search');
            const results = document.getElementById('search-results');
            const manualEntries = <?php echo json_encode($manualSearchIndex, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const stopWords = new Set(['a', 'ao', 'as', 'como', 'da', 'de', 'do', 'e', 'em', 'na', 'no', 'o', 'os', 'para', 'por', 'que', 'um', 'uma']);
            const normalise = value => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-PT');
            const escapeHtml = value => value.replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
            const closeSearch = () => { results.innerHTML = ''; searchInput.setAttribute('aria-expanded', 'false'); };
            const nextStepLinks = '<div class="search-next-step"><span>Não encontraste a resposta?</span><span class="search-next-step-links"><a href="/forum/">Fórum</a><a href="/ai">IA do Manual</a></span></div>';
            const renderResults = entries => {
                const directResults = entries.map(entry => `<a class="search-result" role="option" href="${escapeHtml(entry.url)}"><small>${escapeHtml(entry.type)}</small><strong>${escapeHtml(entry.title)}</strong><span class="search-result-description">${escapeHtml(entry.description)}</span></a>`).join('');
                results.innerHTML = directResults || '<div class="search-empty">Não encontrámos um resultado direto no Manual.</div>';
                results.insertAdjacentHTML('beforeend', nextStepLinks);
                searchInput.setAttribute('aria-expanded', 'true');
            };
            const scoreEntry = (entry, query, words) => {
                const title = normalise(entry.title);
                const terms = normalise(entry.terms);
                const description = normalise(entry.description);
                let score = title.includes(query) || terms.includes(query) ? 10 : 0;
                words.forEach(word => {
                    if (title.includes(word)) score += 7;
                    if (terms.includes(word)) score += 4;
                    if (description.includes(word)) score += 2;
                });
                return score;
            };
            const performSearch = () => {
                const query = normalise(searchInput.value.trim());
                if (query.length < 2) { closeSearch(); return; }
                const words = query.split(/\s+/).filter(word => word.length > 1 && !stopWords.has(word));
                const entries = manualEntries.map(entry => ({ entry, score: scoreEntry(entry, query, words) }))
                    .filter(result => result.score > 0)
                    .sort((left, right) => right.score - left.score)
                    .slice(0, 6)
                    .map(result => result.entry);
                renderResults(entries);
            };
            searchInput.addEventListener('input', performSearch);
            searchInput.addEventListener('keydown', event => { if (event.key === 'Escape') { closeSearch(); searchInput.blur(); } });
            document.addEventListener('keydown', event => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); searchInput.focus(); } });
            document.addEventListener('click', event => { if (!event.target.closest('.quick-search') && !event.target.closest('.hero-search-results')) closeSearch(); });
        })();
    </script>
</body>
</html>
