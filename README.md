# Manual de Impressão 3D — Ecosystem (Professional Cloud Edition)

Uma plataforma educativa completa e gamificada para entusiastas de fabricação aditiva, integrando um Manual Técnico interativo, Fórum de Comunidade, Ferramentas Maker e um Assistente de Inteligência Artificial especializado.

> **Domínio Oficial:** [https://manual-3d.pt](https://manual-3d.pt)  
> **Status:** Produção ativa em infraestrutura Cloud robusta (Render + Aiven).

## 🚀 Tecnologias & Infraestrutura

- **Backend:** PHP 8.2 (Containerizado via Docker)
- **Frontend:** HTML5, CSS3 (Modern UI c/ Syne & Space Mono), JavaScript Vanilla
- **Base de Dados:** MySQL 8.0 hospedado no **Aiven** (Alta disponibilidade)
- **Email:** **Resend API** para comunicações transacionais (Boas-vindas, Recuperação, Segurança)
- **IA:** **Groq / Llama 3** para processamento de linguagem natural focado em 3D
- **Hospedagem:** **Render.com** (Auto-deploy via GitHub)

## 🎯 Funcionalidades Principais

### 1. Manual Educativo Interativo
- **Modos de Dificuldade:** Alternância dinâmica entre modo "Iniciante" (simples) e "Avançado" (técnico).
- **Categorias:** 11 capítulos detalhados, desde a introdução até à manutenção de hardware.
- **Base de Dados de Materiais:** Fichas técnicas de PLA, PETG, ABS, ASA, TPU e Nylon.
- **Ferramentas Maker:** Calculadoras de custo de filamento e estimativa de consumo de energia.

### 2. Gamificação & Comunidade
- **Planta Maker:** Sistema de crescimento visual onde o progresso do utilizador evolui uma planta/árvore (GP - Growth Points).
- **Sistema de XP:** Ganho de experiência através de leitura, participação no fórum e missões diárias.
- **Loja de Itens:** Personalização de perfis com Molduras (Frames), Cores de Destaque e Emblemas (Badges).
- **Fórum Global:** Comunidades segmentadas por temas (Troubleshooting, Projetos, Notícias).

### 3. Inteligência Artificial (Print AI)
- **Assistente Contextual:** Chatbot que entende em que secção do manual o utilizador está.
- **Modo Técnico:** IA treinada para responder a falhas de impressão (Warping, Stringing, Under-extrusion).

## 🛡️ Governação & Administração

### Painel de Moderação Master
- **Dashboard Global:** Métricas em tempo real de toda a plataforma (utilizadores, posts, saúde da planta).
- **Sistema de "Carta de Condução":** Moderação justa baseada em pontos de infração (Avisos, Suspensões, Banimentos).
- **Controlo de Acessos:** Gestão centralizada de utilizadores restritos e reativação de contas.
- **Gestão de Inventário:** Ferramenta master para adicionar novos itens à loja global.

### Conformidade & Segurança
- **RGPD Ready:** Consentimento explícito no registo e ferramentas de "Direito ao Esquecimento" (eliminação total de dados).
- **SEO & Monetização:** Configurado com Google Search Console, `sitemap.xml` e Google AdSense integrado.
- **Segurança:** Encriptação BCRYPT, proteção CSRF e comunicações via SSL/HTTPS.

## 📋 Requisitos de Deploy (Environment Variables)

Para o funcionamento pleno em produção, configurar as seguintes chaves:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`: Credenciais Aiven.
- `RESEND_API_KEY`: Token de envio de email.
- `GROQ_API_KEY`: Chave para o motor de IA.
- `GOOGLE_CLIENT_ID`: Para o sistema de Login com Google.

---
**Contacto:** [contacto@manual-3d.pt](mailto:contacto@manual-3d.pt)  
**Parcerias:** [parcerias@manual-3d.pt](mailto:parcerias@manual-3d.pt)

**Desenvolvido por Martim — PAP 2025/2026 (Nota: 20)**
*Uma plataforma desenhada para transformar a aprendizagem da impressão 3D numa jornada interativa e profissional.*
