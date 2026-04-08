# Manual de Impressão 3D — Ecosystem (Cloud Edition)

Sistema web completo e inteligente para entusiastas de Impressão 3D, integrando um Manual Técnico, Fórum de Comunidade e um Assistente de IA avançado.

> **Status:** Migrado para infraestrutura Cloud (Render + Aiven) com IA Gemini 2.0 Flash.

## 🚀 Tecnologias & Infraestrutura

- **Backend:** PHP 8.2 (Containerizado via Docker)
- **Frontend:** HTML5, CSS3 (Modern UI c/ Syne & Space Mono fonts), JS Vanilla
- **Base de Dados:** MySQL 8.0 hospedado no **Aiven** (exige SSL e Primary Keys)
- **Inteligência Artificial:** **Google Gemini 2.0 Flash** (via OpenAI-compatible endpoint)
- **Hospedagem:** **Render.com** (Ambiente efêmero c/ variáveis de ambiente)

## 🤖 Sistema de IA (Print AI)

O projeto conta com uma integração de IA robusta baseada em protocolos de personalidade:

- **Modo Manual:** Assistente flutuante focado em dúvidas rápidas e troubleshooting.
- **Modo Fórum:** Especialista em regras da comunidade e suporte ao utilizador.
- **Modo Assistente (Full):** Página dedicada com histórico de conversas, contexto de secção e modos "Iniciante" vs "Avançado".
- **Motor:** Gemini 2.0 Flash para respostas ultrarrápidas e precisas em PT-PT.

## 📋 Requisitos de Deploy

### Variáveis de Ambiente (Configurar no Render/Produção)
- `DB_HOST`: Host do cluster Aiven.
- `DB_PORT`: Porta (geralmente 14810 no Aiven).
- `DB_NAME`: Nome da base de dados.
- `DB_USER`: Utilizador da BD.
- `DB_PASS`: Password da BD.
- `GEMINI_API_KEY`: Chave da Google AI Studio.

### Docker
O projeto inclui um `Dockerfile` otimizado para o Render:
- Base: `php:8.2-apache`
- Extensões: `pdo_mysql`, `gd`, `zip`
- Config: `mod_rewrite` ativo para URLs amigáveis.

## 📁 Estrutura do Projeto

```
├── api/                    # Endpoints da IA e Comentários
├── config/                 # Configurações de BD e IA (Environment based)
├── forum/                  # Sistema completo de fórum e comunidades
├── includes/               # Funções core e segurança
├── sql/                    # Scripts de migração e estrutura de dados
├── uploads/                # Armazenamento de mídia (Efêmero em containers)
├── Dockerfile              # Definição do container de deploy
└── index.php               # Landing page e Manual 3D
```

## 🔐 Segurança & Robustez

- **SSL Obrigatório:** Conexão com a base de dados via Aiven com verificação de certificado.
- **Primary Keys:** Todas as tabelas (incluindo as do fórum) garantem PKs para compatibilidade com clusters MySQL modernos.
- **CSRF & Sanitização:** Proteção em todos os inputs e formulários de IA/Fórum.
- **Rate Limiting:** Limites de mensagens de IA por sessão para controlo de custos/tokens.

## 🛠️ Instalação Local

1. Clone o repositório.
2. Configure as variáveis de ambiente no seu `.env` ou servidor local.
3. Utilize o `install.php` ou importe os ficheiros em `/sql/`.
4. Certifique-se de que o `mod_rewrite` está ativo.

---
**Criado por Martim (Purpl3F0x)** — Uma evolução do Manual 3D original para uma arquitetura cloud moderna e inteligente.
