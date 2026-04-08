# Hotspot MikroTik com MySQL - Charles WiFi

Sistema profissional de Hotspot para MikroTik com cadastro de usuários via banco de dados MySQL/MariaDB.

## Funcionalidades

- **Tela de configuração visual** - Configure tudo via interface web do admin
- **Login profissional** - Design moderno com gradientes, animações e responsivo
- **Painel administrativo** - Dashboard com estatísticas, gerenciamento de usuários e exportação CSV
- **Segurança** - Senhas com hash, prepared statements, proteção contra SQL injection e XSS
- **Validação de CPF** - Validação real do CPF brasileiro
- **Responsivo** - Funciona perfeitamente em celulares e desktops
- **Auto-redirecionamento** - Redireciona para página de login após cadastro
- **Criação automática no MikroTik** - Usuários criados automaticamente via API
- **Notificações WhatsApp** - Notificações automáticas via Evolution API
- **Mensagem de boas-vindas** - Envio automático de mensagem para o cliente

## Estrutura de Arquivos

```
hotspot_mikrotik_mysql/
├── Mikrotik/                    # Arquivos para o MikroTik Hotspot
│   ├── login.html              # Página de login/cadastro
│   ├── alogin.html             # Página de login realizado
│   ├── status.html             # Status da conexão
│   ├── logout.html             # Página de logout
│   ├── error.html              # Página de erro
│   ├── css/                    # Estilos CSS
│   └── js/                     # Scripts JavaScript
│
├── Externo/                     # Arquivos do servidor web externo
│   ├── admin.php               # Painel administrativo completo
│   ├── login.php               # Processa cadastro de usuários
│   ├── check_login.php         # Verifica login dos usuários
│   ├── config.php              # Configuração centralizada
│   ├── config.ini              # Arquivo de configuração
│   ├── database.sql            # Script SQL para criar o banco
│   ├── validacpf.php           # Validação de CPF
│   └── ...
│
└── README.md                   # Este arquivo
```

## Instalação

### Requisitos

- Servidor web com PHP 7.4+ (Apache, Nginx, etc.)
- MySQL 5.7+ ou MariaDB 10.3+
- MikroTik RouterOS com Hotspot configurado

### Passo 1 - Configurar o Servidor Web

1. Copie os arquivos da pasta `Externo/` para seu servidor web
2. Acesse `http://seu-servidor/admin.php?action=settings`
3. Preencha as configurações:
   - **Banco de Dados**: Host, usuário, senha, nome do banco
   - **Hotspot**: Nome da rede, cores, logo
   - **MikroTik**: IP do router, usuário e senha da API
   - **Admin**: Usuário e senha do painel administrativo
4. Clique em "Salvar Configuração"

### Passo 2 - Configurar o MikroTik

1. Acesse o MikroTik via WinBox ou WebFig
2. Vá em **IP > Hotspot**
3. Configure o Hotspot Server Profile:
   - Na aba **Login**, marque `HTTP CHAP` e `HTTP PAP`
   - Na aba **General**, defina o endereço do servidor externo em `HTML Directory`
4. Envie os arquivos da pasta `Mikrotik/` para o diretório do Hotspot no router
5. Configure o `login.html` para apontar para seu servidor externo:
   ```html
   <form method="post" action="http://seu-servidor/login.php">
   ```

### Passo 3 - Configurar Redirecionamento

No MikroTik, configure o Walled Garden para permitir acesso ao servidor externo e recursos externos sem autenticação:

```
/ip hotspot walled-garden
add action=allow dst-host=seu-servidor.com.br
add action=allow dst-host=*.seu-servidor.com.br
add action=allow dst-host=fonts.googleapis.com
add action=allow dst-host=cdnjs.cloudflare.com
add action=allow dst-host=gstatic.com
```

**Nota:** Substitua `seu-servidor.com.br` pelo domínio do seu servidor PHP.

### Passo 4 - Abrir Porta da API (opcional)

Para criar usuários automaticamente no MikroTik, abra a porta 8728:

```
/ip service enable api
```

## Painel Administrativo

Acesse `http://seu-servidor/admin.php` para:

- **Dashboard** - Estatísticas gerais (total de usuários, ativos, bloqueados, cadastros do dia)
- **Usuários** - Buscar, bloquear/desbloquear e remover usuários
- **Online** - Ver usuários conectados agora (auto-refresh a cada 30s)
- **Configurações** - Alterar todas as configurações do sistema
- **Exportar CSV** - Exportar todos os dados dos usuários

**Credenciais padrão:**
- Usuário: `admin`
- Senha: `admin123` (troque no primeiro acesso!)

## WhatsApp (Evolution API)

O sistema pode enviar notificações WhatsApp via Evolution API.

### Configuração

No painel administrativo, seção **WhatsApp (Evolution API)**:

| Campo | Descrição |
|-------|-----------|
| URL da API | URL da sua Evolution API (ex: https://api.suaempresa.com.br) |
| Nome da Instância | Nome da instância do WhatsApp |
| API Key | Chave da API |
| Tipo de Mensagem | Texto ou Imagem |
| URL da Imagem | URL da imagem (para tipo imagem) |
| Modelo da Mensagem | Modelo com variáveis: `{nome}`, `{cpf}`, `{email}`, `{telefone}` |
| Números para Notificar | Números para receber notificação (DDD + número, separados por vírgula) |

### Mensagem para Cliente

Na seção **WhatsApp - Mensagem para Cliente**:

| Campo | Descrição |
|-------|-----------|
| Ativar Mensagem de Boas-Vindas | Ativa envio de mensagem automática para o cliente |
| Tipo de Mensagem | Texto ou Imagem |
| Mensagem de Boas-Vindas | Template da mensagem com variáveis |

### Variáveis disponíveis

- `{nome}` - Nome completo do cliente
- `{cpf}` - CPF do cliente
- `{email}` - Email do cliente
- `{telefone}` - Telefone do cliente

## Banco de Dados

O script `database.sql` cria as seguintes tabelas:

| Tabela | Descrição |
|--------|-----------|
| `dados` | Cadastro dos usuários (CPF, nome, sobrenome, email, telefone, MAC, IP) |
| `logs_conexao` | Registro de conexões dos usuários |
| `estatisticas` | Estatísticas diárias de uso |

## Segurança

- Senhas de admin com hash `password_hash()` (bcrypt)
- Prepared statements em todas as consultas SQL
- Sanitização de inputs com `htmlspecialchars()`
- Validação de CPF com algoritmo oficial
- Proteção contra SQL injection e XSS

## Personalização

### Cores

Altere as cores no painel administrativo ou diretamente no `config.ini`:
- `hotspot_primary_color` - Cor principal (gradiente)
- `hotspot_secondary_color` - Cor secundária (gradiente)

### Logo

Coloque sua logo na pasta `Mikrotik/` com o nome configurado (padrão: `logo.png`).

### Nome da Rede

Altere o `hotspot_name` nas configurações para mudar o nome exibido nas páginas.

## Troubleshooting

### Erro de conexão com banco de dados
- Verifique se o MySQL está rodando
- Confirme as credenciais no `config.ini`
- Verifique se o banco existe

### Página de login não aparece
- Verifique se os arquivos estão no diretório correto do MikroTik
- Confirme o Walled Garden para o servidor externo
- Verifique se o formulário aponta para a URL correta

### Usuários não aparecem na aba Online
- Verifique se a porta 8728 (API) está aberta no MikroTik
- Verifique se o usuário e senha da API estão corretos nas configurações

### WhatsApp não envia mensagens
- Verifique se a Evolution API está online
- Confirme o nome da instância e API Key
- Para números brasileiros, o DDI 55 é adicionado automaticamente

### CPF já cadastrado
- O sistema não permite CPF duplicado
- Oriente o usuário a fazer login em vez de cadastrar novamente

## Licença

Este projeto é open source e pode ser usado livremente.

## Autor

Desenvolvido por [Charles](https://github.com/linkincharles) - RedesLinkin
