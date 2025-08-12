# MusicFLAC - Guida Completa all'Installazione

## Panoramica

MusicFLAC è un'applicazione web che permette di scaricare tracce musicali da Spotify in formato FLAC ad alta qualità utilizzando servizi come Tidal, Amazon e Qobuz.

## Requisiti di Sistema

- PHP 7.4 o superiore
- Estensioni PHP necessarie:
  - PDO SQLite
  - cURL
  - Zip
  - OpenSSL
- Server web (Apache, Nginx, ecc.)
- Accesso a Internet

## Struttura dell'Applicazione

```
MusicFLAC/
├── admin/              # Area amministrativa
├── data/               # Directory del database (deve essere scrivibile)
├── downloads/          # Directory dei file scaricati (deve essere scrivibile)
├── includes/           # File di configurazione e funzioni
├── installer/          # Script di installazione
├── js/                 # File JavaScript
├── api.php             # API principale
├── index.php           # Pagina principale
├── login.php           # Pagina di login
├── logout.php          # Logout
├── serve.php           # Servizio file
├── signup.php          # Registrazione utenti
├── tracks.php          # Visualizzazione tracce
└── ...                 # Altri file dell'applicazione
```

## Installazione Passo-Passo

### 1. Preparazione dell'Ambiente

1. **Caricamento dei file**
   - Carica tutti i file dell'applicazione sul tuo server web
   - Assicurati che la struttura delle directory sia mantenuta

2. **Impostazione dei permessi**
   Le seguenti directory devono essere scrivibili dal server web:
   ```bash
   chmod 755 data/
   chmod 755 downloads/
   ```

### 2. Esecuzione dell'Installer

1. **Accesso all'installer**
   Apri il tuo browser e vai all'indirizzo:
   ```
   http://tuodominio.it/installer/
   ```

2. **Passo 1: Verifica dei permessi**
   - L'installer verificherà automaticamente i permessi delle directory
   - Se ci sono problemi, segui le indicazioni per risolverli

3. **Passo 2: Creazione utente amministratore**
   - Inserisci un nome utente per l'account amministratore
   - Inserisci un indirizzo email valido
   - Scegli una password sicura (minimo 6 caratteri)
   - Conferma la password

4. **Passo 3: Completamento**
   - L'installer creerà il database e l'utente amministratore
   - Al termine, verrai reindirizzato al login

### 3. Primo Accesso

1. **Login amministratore**
   Vai all'indirizzo:
   ```
   http://tuodominio.it/login.php
   ```
   Usa le credenziali create durante l'installazione.

2. **Configurazione dei servizi**
   Dopo il login, accedi all'area amministrativa:
   ```
   http://tuodominio.it/admin/
   ```

### 4. Configurazione dei Servizi

#### Tidal
1. Accedi a Tidal Web con il tuo account
2. Apri gli strumenti per sviluppatori del browser (F12)
3. Vai alla sezione Network
4. Trova una richiesta a `api.tidal.com` e copia:
   - `X-Tidal-Token` (token)
   - `Country-Code` (country_code)
   - `X-Tidal-SessionID` (session_id)

#### Qobuz
1. Accedi a Qobuz Web con il tuo account
2. Apri gli strumenti per sviluppatori del browser (F12)
3. Vai alla sezione Network
4. Trova una richiesta a `www.qobuz.com/api.json` e copia:
   - `X-App-Id` (app_id)
   - `X-User-Auth-Token` (user_auth_token)

#### Amazon
1. Amazon Music richiede un token speciale ottenuto tramite l'applicazione Android
2. Usa strumenti di reverse engineering per ottenere il token

### 5. Aggiunta dei Token

1. Nell'area amministrativa, vai a "Gestisci token"
2. Seleziona il servizio per cui vuoi aggiungere il token
3. Inserisci i valori ottenuti nei passaggi precedenti
4. Salva le impostazioni

## Sicurezza

### Protezione dell'Area Amministrativa
- Cambia regolarmente la password dell'utente amministratore
- Non condividere le credenziali con utenti non autorizzati
- Usa password complesse

### Protezione dei File
- Il file del database (`data/app.sqlite`) è protetto da accesso diretto
- Verifica che il file `.htaccess` nella directory `data/` sia attivo

### Aggiornamenti
- Tieni l'applicazione aggiornata con le ultime versioni
- Verifica regolarmente la presenza di aggiornamenti di sicurezza

## Utilizzo dell'Applicazione

### Download di Tracce
1. Vai alla pagina principale dell'applicazione
2. Incolla l'URL di una traccia, album o playlist Spotify
3. Seleziona il servizio di download preferito
4. Clicca su "Recupera"
5. Scegli se scaricare una singola traccia o l'intero album/playlist
6. Il file verrà preparato e scaricato automaticamente

### Gestione Utenti (solo Admin)
- Approvazione nuovi utenti
- Promozione di utenti a amministratori
- Visualizzazione statistiche di download

### Statistiche
- Monitoraggio dei download
- Visualizzazione utenti attivi
- Analisi dei servizi più utilizzati

## Risoluzione dei Problemi

### Errore "Directory non scrivibile"
**Soluzione**: Verifica i permessi delle directory `data/` e `downloads/`:
```bash
chmod 755 data/
chmod 755 downloads/
```

### Errore database
**Soluzione**: 
1. Elimina il file `data/app.sqlite`
2. Riesegui l'installer

### Problemi con i download
1. Verifica che i token API siano validi
2. Controlla la connessione internet del server
3. Verifica che le estensioni PHP necessarie siano installate

### L'installer non si carica
1. Verifica che tutti i file siano stati caricati correttamente
2. Controlla i permessi della directory `installer/`
3. Assicurati che PHP sia correttamente configurato sul server

## Supporto

Per problemi con l'installazione o l'utilizzo dell'applicazione:
1. Consulta questa guida
2. Verifica i requisiti di sistema
3. Controlla i log di errore del server web
4. Contatta il supporto tecnico se il problema persiste