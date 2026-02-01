# CVerify Relay Server

Server centrale per il relay di richieste di validazione e attestazioni tra utenti e aziende.

## Struttura

```
relay-server/
├── index.php           # Homepage con statistiche
├── feed.php            # Pagina feed pubblico
├── config.php          # Configurazione e helpers
├── .htaccess           # URL rewriting
├── api/
│   ├── request.php     # POST - Invia richiesta validazione
│   ├── attestation.php # POST - Pubblica attestazione
│   ├── pending.php     # GET - Recupera pending per azienda
│   ├── attestations.php# GET - Recupera attestazioni per utente
│   ├── feed.php        # GET - Feed pubblico
│   └── acknowledge.php # DELETE - Rimuovi pending processato
└── data/
    ├── pending/        # Richieste in attesa (per azienda)
    ├── attestations/   # Attestazioni (per utente)
    └── feed.json       # Feed pubblico
```

## API Endpoints

### POST /api/request

Invia una richiesta di validazione (utente → azienda)

```json
{
  "user_domain": "mario.rossi.it",
  "company_domain": "azienda.com",
  "experience_id": "exp_123456",
  "experience_data": {
    "role": "Software Engineer",
    "start_date": "2020-01-01",
    "end_date": "2023-12-31",
    "description": "..."
  },
  "signature": "base64...",
  "callback_url": "https://mario.rossi.it/cverify/callback"
}
```

### POST /api/attestation

Pubblica un'attestazione firmata (azienda → utente)

```json
{
    "version": "1.0",
    "type": "work_experience_attestation",
    "issuer_domain": "azienda.com",
    "user_domain": "mario.rossi.it",
    "experience_id": "exp_123456",
    "experience_data": {...},
    "experience_hash": "sha256...",
    "issued_at": "2024-01-30T10:00:00Z",
    "valid_until": "2034-01-30T10:00:00Z",
    "attestation_id": "att_abc123",
    "signature": "base64..."
}
```

### GET /api/pending/{domain}

Recupera richieste pending per un'azienda

```
GET /api/pending/azienda.com
```

### GET /api/attestations/{domain}

Recupera attestazioni per un utente

```
GET /api/attestations/mario.rossi.it
```

### GET /api/feed

Feed pubblico di tutte le attività

```
GET /api/feed?limit=50&offset=0&type=attestation
```

### DELETE /api/acknowledge

Rimuovi una richiesta pending dopo averla processata

```json
{
  "request_id": "req_abc123",
  "company_domain": "azienda.com"
}
```

## Deploy

1. Caricare la cartella `relay-server` sul server
2. Assicurarsi che la cartella `data/` sia scrivibile
3. Configurare il dominio (es. `relay.cverify.com`)
4. Aggiornare l'URL nelle dashboard utente/azienda

## Flusso

```
┌─────────┐                    ┌─────────────┐                    ┌─────────┐
│  USER   │                    │   RELAY     │                    │ COMPANY │
└────┬────┘                    └──────┬──────┘                    └────┬────┘
     │                                │                                │
     │  POST /api/request             │                                │
     │ ─────────────────────────────> │                                │
     │                                │  Salva in pending/{company}    │
     │                                │                                │
     │                                │  GET /api/pending/{domain}     │
     │                                │ <───────────────────────────── │
     │                                │                                │
     │                                │  Approva/Rifiuta               │
     │                                │                                │
     │                                │  POST /api/attestation         │
     │                                │ <───────────────────────────── │
     │                                │  Salva in attestations/{user}  │
     │                                │                                │
     │  GET /api/attestations/{me}    │                                │
     │ ─────────────────────────────> │                                │
     │  Riceve attestazione           │                                │
     │                                │                                │
```

## Sicurezza

- Le richieste dovrebbero essere firmate con la chiave privata dell'utente
- Le attestazioni sono firmate con la chiave privata dell'azienda
- La verifica delle firme può essere fatta recuperando le chiavi pubbliche dal DNS
- Il relay server NON verifica le firme, è solo un intermediario
- La cartella `data/` è protetta da accesso diretto via `.htaccess`
