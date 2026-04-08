# Manual de Impressão 3D - Sistema Web

Sistema web completo para o Manual de Impressão 3D com registo de utilizadores, base de dados e secção de comentários/dúvidas.

## 📋 Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache/Nginx)
- Extensões PHP: PDO, PDO_MySQL

## 🚀 Instalação

### 1. Configurar a Base de Dados

1. Crie uma base de dados MySQL:
```sql
CREATE DATABASE manual_3d CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importe o ficheiro `database.sql`:
```bash
mysql -u root -p manual_3d < database.sql
```

Ou use o phpMyAdmin para importar.

### 2. Configurar a Ligação à BD

Edite o ficheiro `config/database.php` com as suas credenciais:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'manual_3d');
define('DB_USER', 'root');
define('DB_PASS', 'sua_password');
```

### 3. Configurar o Servidor Web

#### Apache (.htaccess já incluído)

O ficheiro `.htaccess` já está configurado. Certifique-se de que o `mod_rewrite` está ativado:

```bash
sudo a2enmod rewrite
sudo service apache2 restart
```

#### Nginx

Adicione ao seu servidor block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

### 4. Permissões

Certifique-se de que as pastas têm as permissões corretas:

```bash
chmod -R 755 /caminho/para/o/projeto
chown -R www-data:www-data /caminho/para/o/projeto
```

## 📁 Estrutura do Projeto

```
├── api/
│   └── comments.php          # API para comentários
├── config/
│   └── database.php          # Configuração da BD
├── includes/
│   └── functions.php         # Funções auxiliares
├── index.php                 # Página principal
├── login.php                 # Página de login
├── register.php              # Página de registo
├── logout.php                # Logout
├── database.sql              # Estrutura da BD
├── .htaccess                 # Configuração Apache
└── README.md                 # Este ficheiro
```

## 🗄️ Estrutura da Base de Dados

### Tabelas Principais

- **users** - Utilizadores registados
- **comments** - Comentários/dúvidas
- **replies** - Respostas aos comentários
- **likes** - Sistema de likes
- **user_sessions** - Sessões persistentes
- **activity_logs** - Logs de atividade

## 👤 Conta de Teste

Após a instalação, pode usar a conta de administrador:

- **Username:** admin
- **Password:** admin123

## 🔐 Funcionalidades de Segurança

- Passwords hasheadas com bcrypt
- Proteção CSRF em todos os formulários
- Sessões seguras
- Prepared statements contra SQL Injection
- Sanitização de input/output
- Logs de atividade

## 🎨 Funcionalidades

### Para Utilizadores
- Registo e login
- Modo Iniciante/Avançado
- Pesquisa no conteúdo
- Publicar dúvidas, problemas e dicas
- Responder a outros utilizadores
- Sistema de likes
- Filtros por categoria

### Para Administradores
- Gestão de utilizadores
- Moderação de comentários
- Visualização de logs
- Estatísticas da comunidade

## 📝 Personalização

### Cores
Edite as variáveis CSS no início de cada ficheiro:

```css
:root {
    --accent: #00e5ff;    /* Cor principal */
    --accent2: #ff6b35;   /* Cor secundária */
    --accent3: #7c3aed;   /* Cor terciária */
}
```

## 🔧 Resolução de Problemas

### Erro de conexão à BD
Verifique as credenciais em `config/database.php`

### Páginas não encontradas (404)
Verifique se o `mod_rewrite` está ativado no Apache

### Sessões não funcionam
Verifique se `session.save_path` está configurado corretamente no php.ini

## 📄 Licença

Este projeto é educativo e pode ser livremente partilhado para fins educativos.

## 🤝 Contribuir

Para contribuir com o projeto:
1. Faça um fork
2. Crie uma branch (`git checkout -b feature/nova-funcionalidade`)
3. Commit as alterações (`git commit -am 'Adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Crie um Pull Request

---

Criado para Professores e Alunos de Impressão 3D 🎓
