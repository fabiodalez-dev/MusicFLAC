# MusicFLAC - Installazione

## Requisiti di sistema

- PHP 7.4 o superiore con supporto SQLite3
- Estensione PDO SQLite abilitata
- Estensione cURL abilitata
- Estensione Zip abilitata
- Estensione OpenSSL abilitata (per le connessioni HTTPS)

## Installazione

### 1. Caricamento dei file

1. Carica tutti i file dell'applicazione sul tuo server web
2. Assicurati che le seguenti directory siano scrivibili:
   - `data/` (per il database)
   - `downloads/` (per i file scaricati)

Puoi rendere le directory scrivibili con i seguenti comandi (su sistemi Unix/Linux):

```bash
chmod 755 data/
chmod 755 downloads/
```

### 2. Esecuzione dell'installer

1. Accedi alla directory dell'installer tramite browser:
   ```
   http://tuodominio.it/installer/install.php
   ```

2. Segui i passaggi dell'installer:
   - **Passo 1**: Verifica dei permessi delle directory
   - **Passo 2**: Creazione dell'utente amministratore
   - **Passo 3**: Completamento dell'installazione

### 3. Configurazione post-installazione

Dopo aver completato l'installazione:

1. Accedi all'applicazione con le credenziali dell'amministratore
2. Vai all'area amministrativa (`/admin/`)
3. Configura i servizi di download che desideri utilizzare
4. Aggiungi i token API necessari per ciascun servizio

## Configurazione dei servizi

### Tidal
1. Accedi a Tidal Web
2. Apri gli strumenti per sviluppatori del browser (F12)
3. Vai alla sezione Network
4. Trova una richiesta a `api.tidal.com` e copia i seguenti valori:
   - `X-Tidal-Token` (token)
   - `Country-Code` (country_code)
   - `X-Tidal-SessionID` (session_id)

### Qobuz
1. Accedi a Qobuz Web
2. Apri gli strumenti per sviluppatori del browser (F12)
3. Vai alla sezione Network
4. Trova una richiesta a `www.qobuz.com/api.json` e copia i seguenti valori:
   - `X-App-Id` (app_id)
   - `X-User-Auth-Token` (user_auth_token)

### Amazon
1. Amazon Music richiede un token speciale ottenuto tramite l'applicazione Android
2. Usa strumenti di reverse engineering per ottenere il token

## Sicurezza

### Protezione dell'area amministrativa
- Cambia regolarmente la password dell'utente amministratore
- Non condividere le credenziali con utenti non autorizzati
- Usa password complesse

### Protezione dei file
- Assicurati che il file del database (`data/app.sqlite`) non sia accessibile direttamente via web
- Configura il server per impedire l'accesso diretto alla directory `data/`

### Aggiornamenti
- Tieni l'applicazione aggiornata con le ultime versioni
- Verifica regolarmente la presenza di aggiornamenti di sicurezza

## Risoluzione dei problemi

### Errore "Directory non scrivibile"
Soluzione: Verifica i permessi delle directory `data/` e `downloads/` come indicato nella sezione Installazione.

### Errore database
Soluzione: Elimina il file `data/app.sqlite` e riesegui l'installer.

### Problemi con i download
1. Verifica che i token API siano validi
2. Controlla la connessione internet del server
3. Verifica che le estensioni PHP necessarie siano installate

## Supporto

Per problemi con l'installazione o l'utilizzo dell'applicazione, consulta la documentazione o contatta il supporto tecnico.