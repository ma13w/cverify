# CVerify - Professional Credential Verification

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-blue?style=flat-square" alt="PHP">
  <img src="https://img.shields.io/badge/Tailwind-CSS-38B2AC?style=flat-square" alt="Tailwind">
  <img src="https://img.shields.io/badge/Crypto-RSA--2048-green?style=flat-square" alt="RSA">
</p>

Un sistema decentralizzato per la verifica delle credenziali professionali basato su DNS e crittografia RSA.

## 🎯 Concetto

CVerify permette ai professionisti di costruire un CV verificabile crittograficamente:

- **Nessuna autorità centrale** - Le identità sono verificate via DNS
- **Firma digitale** - Le aziende firmano le attestazioni con RSA-2048
- **Verifica pubblica** - Chiunque può verificare matematicamente le credenziali

## 🏗️ Architettura

```
┌─────────────┐     Request      ┌─────────────┐
│    User     │ ───────────────> │   Company   │
│  (dominio)  │ <─────────────── │  (dominio)  │
└─────────────┘   Attestation    └─────────────┘
      │                                │
      │         DNS TXT Records        │
      │   ┌───────────────────────┐    │
      └───│   _cverify.domain.com │────┘
          │   cverify-id=...      │
          │   cverify-key-0=...   │
          └───────────────────────┘
                      │
                      ▼
            ┌─────────────────┐
            │    Verifier     │
            │ (verifica JSON) │
            └─────────────────┘
```

## 📁 Struttura Progetto

```
cverify/
├── index.php                 # Landing page
├── includes/
│   ├── header.php           # Navigazione comune
│   └── footer.php           # Footer comune
├── src/
│   ├── Crypto.php           # Operazioni RSA
│   └── DNS.php              # Verifica DNS
├── user/
│   ├── dashboard.php        # Dashboard utente
│   ├── cv.json              # CV pubblico
│   ├── validation_callback.php
│   └── data/                # Chiavi private (gitignore!)
├── company/
│   ├── dashboard.php        # Portale HR
│   ├── ping_receiver.php    # API endpoint
│   └── data/                # Attestazioni
└── verifier/
    └── index.php            # Verifier Lens
```

## 🚀 Installazione

### Requisiti

- PHP 8.0+
- Estensione OpenSSL
- Web server (Apache/Nginx)

### Setup

1. Clona il repository nella directory web:

```bash
git clone https://github.com/your-repo/cverify.git /var/www/html/cverify
```

2. Assicurati che le directory `data/` siano scrivibili:

```bash
chmod 755 user/data company/data
```

3. Configura il web server per servire i file PHP.

## 📖 Utilizzo

### Come Utente

1. Vai su **User Dashboard**
2. Inserisci il tuo dominio e genera le chiavi RSA
3. Aggiungi i record DNS TXT mostrati al tuo dominio
4. Aggiungi le tue esperienze lavorative
5. Clicca "Richiedi Validazione" per ogni esperienza

### Come Azienda

1. Vai su **Company Portal**
2. Configura il dominio aziendale e genera le chiavi
3. Aggiungi i record DNS TXT
4. Gestisci le richieste in arrivo: Approva o Rifiuta
5. Le attestazioni firmate vengono salvate e inviate all'utente

### Come Verificatore

1. Vai su **Verifier Lens**
2. Incolla l'URL del cv.json di un utente
3. Visualizza le credenziali verificate con badge "Verified by [domain]"
4. Clicca sui badge per vedere i dettagli crittografici

## 🔐 Record DNS

### Formato Record

```
_cverify.tuodominio.com TXT "cverify-id=FINGERPRINT_SHA256"
_cverify.tuodominio.com TXT "cverify-key-0=CHIAVE_PARTE_1"
_cverify.tuodominio.com TXT "cverify-key-1=CHIAVE_PARTE_2"
...
```

### Esempio CloudFlare

| Type | Name      | Content                   |
| ---- | --------- | ------------------------- |
| TXT  | \_cverify | cverify-id=abc123...      |
| TXT  | \_cverify | cverify-key-0=MIIBIjAN... |

## 🔧 API Endpoints

### User Validation Request

```
POST /user/validation_callback.php
Content-Type: application/json

{
  "attestation": {...},
  "experience_id": "exp_xxx"
}
```

### Company Validation Receiver

```
POST /company/ping_receiver.php
Content-Type: application/json

{
  "user_domain": "user.example.com",
  "experience_id": "exp_xxx",
  "experience_data": {...},
  "callback_url": "https://...",
  "signature": "base64..."
}
```

## 🛡️ Sicurezza

- **Chiavi Private**: Mai esposte, memorizzate con permessi 0600
- **RSA-2048**: Standard crittografico industriale
- **SHA-256**: Per hash e fingerprint
- **HTTPS**: Raccomandato per tutte le comunicazioni

## 📄 Licenza

MIT License - Vedi [LICENSE](LICENSE) per dettagli.

---

## 🔒 Security

### Security Audit & Fixes

A comprehensive security audit has been conducted and multiple vulnerabilities have been addressed. See [SECURITY_AUDIT_FIXES.md](SECURITY_AUDIT_FIXES.md) for details.

**Key Security Features**:

- ✅ Secure session management (HTTPOnly, Secure, SameSite cookies)
- ✅ Path traversal protection
- ✅ JSON validation and size limits (1MB max)
- ✅ Rate limiting (10 login attempts per 5 min, 20 API requests per min)
- ✅ Challenge-based authentication with expiration (5 min)
- ✅ Secure file permissions (0600 for sensitive data)
- ✅ Atomic file writes with locking (LOCK_EX)
- ✅ Security headers (CSP, X-Frame-Options, etc.)
- ⚠️ HTTPS enforcement (configure in production)
- ⚠️ CSRF protection (tokens available, needs implementation)

**Before Production Deployment**:

1. Read [SECURITY_AUDIT_FIXES.md](SECURITY_AUDIT_FIXES.md) thoroughly
2. Complete all items in the production checklist
3. Uncomment HTTPS enforcement in `.htaccess`
4. Implement CSRF tokens (see [CSRF_IMPLEMENTATION_GUIDE.php](CSRF_IMPLEMENTATION_GUIDE.php))
5. Review private key storage architecture (see security audit)

**Security Reporting**:
If you discover a security vulnerability, please email security@yourdomain.com (do not open public issues).

---

<p align="center">
  <b>CVerify</b> - Decentralized Professional Attestation<br>
  <sub>Built with 🔐 by the open source community</sub>
</p>
