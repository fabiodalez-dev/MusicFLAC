// MusicFLAC Web JavaScript

// Function to format date as dd-MM-YYYY
function formatDate(dateString) {
    if (!dateString) return '';
    
    // Handle different date formats
    let date;
    if (dateString.includes('-')) {
        // Try parsing as YYYY-MM-DD
        const parts = dateString.split('-');
        if (parts.length === 3) {
            // Create date in local timezone
            date = new Date(parts[0], parts[1] - 1, parts[2]);
        } else {
            date = new Date(dateString);
        }
    } else {
        date = new Date(dateString);
    }
    
    // Check if date is valid
    if (isNaN(date.getTime())) {
        return dateString;
    }
    
    // Format as dd-MM-YYYY
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    return `${day}-${month}-${year}`;
}
document.addEventListener('DOMContentLoaded', function() {
    if (window.MF_DEBUG_UI) {
        ensureDebugPane();
    }
    initBurgerMenu();
    initServiceSelection();
    initResponsiveValidation();
    // Auto-run if query params provided
    try {
        const params = new URLSearchParams(window.location.search);
        const preUrl = params.get('spotify_url') || params.get('url') || '';
        const preService = params.get('service') || '';
        if (preUrl) {
            const desktopInput = document.getElementById('spotify_url');
            const mobileInput = document.getElementById('spotify_url_mobile');
            const decoded = safeDecode(preUrl);
            if (desktopInput) desktopInput.value = decoded;
            if (mobileInput) mobileInput.value = decoded;
            if (preService) {
                const svc = preService.toLowerCase();
                const el = document.querySelector(`input[name="service"][value="${svc}"]`);
                if (el) el.checked = true;
            }
            debug('Auto-fetch from query params', { url: decoded, service: preService || '(default)' });
            // Trigger programmatic fetch
            performFetch(decoded, (preService || 'tidal').toLowerCase());
        }
    } catch (e) { debug('Query param parse error', { message: e.message }); }
    // Form submission handling
    const spotifyForm = document.querySelector('#fetchForm');
    if (spotifyForm) {
        spotifyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get URL from whichever input has a value
            const desktopInput = document.getElementById('spotify_url');
            const mobileInput = document.getElementById('spotify_url_mobile');
            
            let url = '';
            if (desktopInput && desktopInput.value.trim()) {
                url = desktopInput.value.trim();
            } else if (mobileInput && mobileInput.value.trim()) {
                url = mobileInput.value.trim();
            }
            
            if (!url) { 
                showError('Inserisci un URL Spotify'); 
                return; 
            }
            
            // Sync values between both inputs
            if (desktopInput) desktopInput.value = url;
            if (mobileInput) mobileInput.value = url;
            
            const service = (document.querySelector('input[name="service"]:checked')||{}).value || 'tidal';

            const submitButton = spotifyForm.querySelector('button[type="submit"]');
            if (submitButton) setButtonLoading(submitButton, true);

            performFetch(url, service, submitButton);
        });
    }
    
    // Download buttons
    const downloadButtons = document.querySelectorAll('button[name="download_track"], button[name="download_album"]');
    downloadButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Preparazione...';
            this.disabled = true;
        });
    });
    
    // Preview player controls
    const playButtons = document.querySelectorAll('button:not([name])');
    playButtons.forEach(button => {
        // Check if the button contains a play icon
        const playIcon = button.querySelector('.fa-play');
        if (playIcon) {
            button.addEventListener('click', function() {
                const player = document.getElementById('audioPlayer');
                if (player) {
                    player.classList.remove('hidden');
                }
            });
        }
    });
});

// Show error message
function showError(message) {
    // Remove existing error modals
    const existingModal = document.querySelector('.fixed.inset-0');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create error modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-2xl p-6 max-w-md w-full mx-4 border border-gray-700">
            <div class="text-red-500 text-2xl mb-4">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Errore</h3>
            <p class="text-gray-300 mb-6">${message}</p>
            <button onclick="this.parentElement.parentElement.remove()" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg w-full font-semibold transition">
                Chiudi
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function renderResults(data, service) {
    debug('Rendering results', { hasTrack: !!data.track, hasAlbum: !!data.album_info, hasPlaylist: !!data.playlist_info });
    const container = document.getElementById('results');
    if (!container) return;
    container.classList.remove('hidden');

    // Utility
    const fmt = ms => {
        const s = Math.floor((ms||0)/1000), m = Math.floor(s/60), r = s%60;
        return `${(''+m).padStart(2,'0')}:${(''+r).padStart(2,'0')}`;
    };
    const esc = (s) => (s||'').toString().replace(/[&<>"]/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));

    let headerHtml = '';
    if (data.track) {
        const t = data.track;
        headerHtml = `
        <div class="bg-gray-800 rounded-2xl p-6 mb-8 shadow-xl border border-gray-700">
          <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/3"><img src="${esc(t.images)}" class="w-full rounded-xl shadow-lg"/></div>
            <div class="md:w-2/3">
              <div class="flex justify-between items-start mb-4">
                <div>
                  <h2 class="text-3xl font-bold">${esc(t.name)}</h2>
                  <p class="text-xl text-gray-300 mt-2">${esc(t.artists)}</p>
                  <p class="text-lg text-gray-400 mt-1">${esc(t.album)}</p>
                </div>
                <span class="bg-green-600 px-3 py-1 rounded-full text-sm">Traccia</span>
              </div>
              <div class="grid grid-cols-2 gap-4 mb-6">
                <div><p class="text-gray-400">Durata</p><p class="text-lg">${fmt(t.duration_ms)}</p></div>
                <div><p class="text-gray-400">Data</p><p class="text-lg">${formatDate(t.release_date)}</p></div>
              </div>
              <div class="flex gap-3">
                <button class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold" data-action="dl-track" data-index="0">Scarica traccia</button>
              </div>
            </div>
          </div>
        </div>`;
    } else if (data.album_info) {
        const a = data.album_info;
        headerHtml = `
        <div class="bg-gray-800 rounded-2xl p-6 mb-8 shadow-xl border border-gray-700">
          <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/3"><img src="${esc(a.images)}" class="w-full rounded-xl shadow-lg"/></div>
            <div class="md:w-2/3">
              <div class="flex justify-between items-start mb-4">
                <div>
                  <h2 class="text-3xl font-bold">${esc(a.name)}</h2>
                  <p class="text-xl text-gray-300 mt-2">${esc(a.artists)}</p>
                </div>
                <span class="bg-purple-600 px-3 py-1 rounded-full text-sm">Album</span>
              </div>
              <div class="grid grid-cols-2 gap-4 mb-6">
                <div><p class="text-gray-400">Data</p><p class="text-lg">${formatDate(a.release_date)}</p></div>
                <div><p class="text-gray-400">Tracce</p><p class="text-lg">${esc(a.total_tracks)}</p></div>
              </div>
              <div class="flex gap-3">
                <button class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold" data-action="dl-album">Scarica album</button>
              </div>
            </div>
          </div>
        </div>`;
    } else if (data.playlist_info) {
        const p = data.playlist_info;
        headerHtml = `
        <div class="bg-gray-800 rounded-2xl p-6 mb-8 shadow-xl border border-gray-700">
          <div class="flex flex-col md:flex-row gap-6">
            <div class="md:w-1/3"><img src="${esc(p.images || 'https://placehold.co/300')}" class="w-full rounded-xl shadow-lg"/></div>
            <div class="md:w-2/3">
              <div class="flex justify-between items-start mb-4">
                <div>
                  <h2 class="text-3xl font-bold">${esc(p.name)}</h2>
                  <p class="text-xl text-gray-300 mt-2">By ${esc((p.owner||{}).display_name||'')}</p>
                </div>
                <span class="bg-green-600 px-3 py-1 rounded-full text-sm">Playlist</span>
              </div>
              <div class="flex gap-3">
                <button class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold" data-action="dl-album">Scarica playlist</button>
              </div>
            </div>
          </div>
        </div>`;
    }

    const tracks = data.track_list || (data.track ? [data.track] : []);
    let listHtml = '';
    if (tracks.length) {
        listHtml = `
        <div class="bg-gray-800 rounded-2xl p-6 shadow-xl border border-gray-700">
          <h3 class="text-2xl font-bold mb-6">Tracce</h3>
          <div class="space-y-4">
            ${tracks.map((t,i)=>`
              <div class="bg-gray-700 rounded-xl p-4 flex items-center hover:bg-gray-600 transition">
                <div class="w-10 text-center text-gray-400 mr-4">${t.track_number || (i+1)}</div>
                <div class="flex-grow">
                  <h4 class="font-semibold">${esc(t.name)}</h4>
                  <p class="text-gray-400 text-sm">${esc(t.artists)} • ${esc(t.album)}</p>
                </div>
                <div class="text-gray-400 mr-4">${fmt(t.duration_ms)}</div>
                <div class="flex gap-2">
                  <button class="bg-green-600 hover:bg-green-700 w-10 h-10 rounded-full flex items-center justify-center transition" data-action="dl-track" data-index="${i}"><i class="fas fa-download"></i></button>
                </div>
              </div>`).join('')}
          </div>
        </div>`;
    }

    container.innerHTML = headerHtml + listHtml;

    // bind buttons
    container.querySelectorAll('[data-action="dl-track"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const i = parseInt(btn.getAttribute('data-index'), 10) || 0;
            const track = tracks[i];
            
            // Show progress bar inline
            let parentElement = btn.closest('.bg-gray-700') || btn.closest('.bg-gray-800');
            if (!parentElement) {
                // For single track downloads, use the parent container
                parentElement = btn.parentElement;
            }
            if (!parentElement) {
                console.error('Cannot find parent element for progress bar');
                return;
            }
            const progressBar = createInlineProgressBar(`Scaricando "${track.name}"...`, parentElement);
            btn.disabled = true; 
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            debug('prepare_track request', { index: i, service });
            fetch((window.API_URL || 'api.php') + '?action=prepare_track', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ service, track })
            }).then(r=>r.json()).then(resp => {
                debug('prepare_track response', resp);
                if (!resp.ok) throw new Error(resp.error||'Errore');
                
                // Complete progress bar
                completeInlineProgressBar(progressBar);
                
                setTimeout(() => {
                    const f = resp.file; 
                    if (f) window.location.href = 'serve.php?f=' + encodeURIComponent(f);
                    removeInlineProgressBar(progressBar);
                }, 1500);
            }).catch(err => {
                debug('prepare_track error', { message: err.message });
                removeInlineProgressBar(progressBar);
                showError('Errore download: ' + err.message);
            }).finally(()=>{
                btn.disabled = false; 
                btn.innerHTML = '<i class="fas fa-download"></i>';
            });
        });
    });

    const dlAlbum = container.querySelector('[data-action="dl-album"]');
    if (dlAlbum) {
        dlAlbum.addEventListener('click', () => {
            const albumName = (data.album_info && data.album_info.name) || (data.playlist_info && data.playlist_info.name) || 'MusicFLAC_Download';
            const type = data.album_info ? 'album' : 'playlist';
            
            // Show progress bar inline
            const albumHeader = dlAlbum.closest('.bg-gray-800');
            const progressBar = createInlineProgressBar(`Preparando ${type} "${albumName}"...`, albumHeader);
            dlAlbum.disabled = true; 
            dlAlbum.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Preparo...';
            
            const jobId = 'album_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            debug('prepare_album request', { tracks: tracks.length, service, albumName, jobId });
            
            // Start time-based progress monitoring (more reliable)
            debug('Starting time-based progress monitoring', { jobId, totalTracks: tracks.length });
            let pollId = startTimeBasedProgress(progressBar, tracks.length);
            
            fetch((window.API_URL || 'api.php') + '?action=prepare_album', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ service, tracks, album: albumName, job_id: jobId })
            }).then(r=>r.json()).then(resp => {
                debug('prepare_album response', resp);
                if (!resp.ok) throw new Error(resp.error||'Errore');
                
                // Complete progress bar
                completeInlineProgressBar(progressBar);
                
                setTimeout(() => {
                    const f = resp.file; 
                    if (f) window.location.href = 'serve.php?f=' + encodeURIComponent(f);
                    removeInlineProgressBar(progressBar);
                }, 1500);
            }).catch(err => {
                debug('prepare_album error', { message: err.message });
                removeInlineProgressBar(progressBar);
                showError('Errore ZIP: ' + err.message);
            }).finally(()=>{
                dlAlbum.disabled = false; 
                dlAlbum.innerHTML = 'Scarica ' + (data.album_info? 'album' : 'playlist');
                if (pollId) clearInterval(pollId);
            });
        });
    }
}

function ensureDebugPane() {
    if (document.getElementById('debugPane')) return;
    const pane = document.createElement('div');
    pane.id = 'debugPane';
    pane.className = 'fixed bottom-4 right-4 w-96 max-h-64 overflow-auto bg-black bg-opacity-70 border border-gray-700 text-accent text-xs p-3 rounded-lg shadow-xl hidden';
    document.body.appendChild(pane);
    const toggler = document.createElement('button');
    toggler.id = 'debugToggle';
    toggler.className = 'fixed bottom-4 right-4 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200 text-xs px-3 py-1 rounded';
    toggler.innerText = 'Debug';
    toggler.addEventListener('click', ()=>{
        pane.classList.toggle('hidden');
    });
    document.body.appendChild(toggler);
}

function debug(msg, obj) {
    if (!window.MF_DEBUG) return; // Suppress all debug logs when disabled
    try { console.log('[MusicFLAC]', msg, obj||''); } catch(e) {}
    const pane = document.getElementById('debugPane');
    if (!pane) return;
    const line = document.createElement('div');
    line.textContent = `[${new Date().toISOString()}] ${msg} ${obj?JSON.stringify(obj):''}`;
    pane.appendChild(line);
}

function performFetch(url, service, submitButton) {
    debug('Submitting fetch_metadata', { url, service });
    const qsBuster = Date.now().toString();
    fetch(((window.API_URL || 'api.php') + '?action=fetch_metadata&_=' + qsBuster), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ url })
    }).then(async r => {
        let data;
        try { data = await r.json(); }
        catch (e) { throw new Error('Risposta non valida dal server'); }
        debug('fetch_metadata response', data);
        if (!data.ok) throw new Error(data.error || 'Errore');
        renderResults(data.data, service);
    }).catch(err => {
        debug('fetch_metadata error', { message: err.message });
        showError('Errore nel recupero: ' + err.message);
    }).finally(() => {
        if (submitButton) setButtonLoading(submitButton, false);
    });
}

function safeDecode(s) {
    try { return decodeURIComponent(s); } catch(e) { return s; }
}

// Play preview
function playPreview() {
    const player = document.getElementById('audioPlayer');
    if (player) {
        player.classList.remove('hidden');
    }
}

// Close player
function closePlayer() {
    const player = document.getElementById('audioPlayer');
    if (player) {
        player.classList.add('hidden');
    }
}

// Format time (seconds to MM:SS)
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
}

// Inline Progress Bar Functions
function createInlineProgressBar(message, parentElement) {
    if (!parentElement) {
        console.error('createInlineProgressBar: parentElement is null');
        return null;
    }
    
    // Remove any existing progress bars in this parent
    const existing = parentElement.querySelector('.inline-progress-bar');
    if (existing) existing.remove();
    
    // Create progress bar element
    const progressContainer = document.createElement('div');
    progressContainer.className = 'inline-progress-bar mt-4 fade-in';
    
    progressContainer.innerHTML = `
        <div class="loader">
            <div class="loading-text text-sm">
                ${message}<span class="dot">.</span><span class="dot">.</span><span class="dot">.</span>
            </div>
            <div class="loading-bar-background">
                <div class="loading-bar" id="progressBar-${Date.now()}">
                    <div class="white-bars-container">
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                        <div class="white-bar"></div>
                    </div>
                </div>
            </div>
            <div class="text-xs text-gray-400 mt-2" id="activeDownloads"></div>
        </div>
    `;
    
    parentElement.appendChild(progressContainer);
    
    // Initialize progress bar at 0% - real progress will be updated by polling
    const progressBar = progressContainer.querySelector('.loading-bar');
    if (progressBar) {
        progressBar.style.width = '0%';
        progressBar.style.transition = 'width 0.3s ease';
        // Clear any existing animations
        progressBar.style.animation = 'none';
        progressBar.classList.remove('loading-animation');
    }
    
    return progressContainer;
}

function startActiveDownloadsPolling(progressContainer) {
    const target = progressContainer ? progressContainer.querySelector('#activeDownloads') : null;
    if (!target) return null;
    const fn = () => {
        fetch(((window.API_URL || 'api.php') + '?action=active_downloads')).then(r => r.json()).then(data => {
            const list = (data && Array.isArray(data.active)) ? data.active : [];
            if (list.length === 0) {
                target.innerHTML = '<span class="text-gray-400">⏳ Preparazione download...</span>';
            } else {
                const trackList = list.slice(0, 2).map(track => {
                    const maxLen = 40;
                    return track.length > maxLen ? track.substring(0, maxLen) + '...' : track;
                });
                target.innerHTML = '<div class="border-t border-gray-600 pt-2 mt-2">' +
                    '<div class="text-xs font-semibold text-blue-400 mb-1">📥 In download:</div>' + 
                    '<div class="flex items-center gap-2">' +
                    '<span class="text-green-400 animate-pulse">⬇️</span>' +
                    '<span class="text-xs">' + trackList.join(', ') + '</span>' +
                    '</div>' +
                    (list.length > 2 ? '<div class="text-xs text-gray-500 mt-1">... e altri ' + (list.length - 2) + ' download</div>' : '') +
                    '</div>';
            }
        }).catch(() => {
            target.innerHTML = '<span class="text-red-400">⚠️ Errore nel monitoraggio</span>';
        });
    };
    
    // Initial call
    fn();
    return setInterval(fn, 1000);
}

function startJobProgressPolling(jobId, progressContainer, totalTracks) {
    const progressBar = progressContainer ? progressContainer.querySelector('.loading-bar') : null;
    const activeDownloads = progressContainer ? progressContainer.querySelector('#activeDownloads') : null;
    const loadingText = progressContainer ? progressContainer.querySelector('.loading-text') : null;
    
    if (!progressContainer) return null;
    
    const updateProgress = (completed, active) => {
        const percentage = Math.min(Math.floor((completed / totalTracks) * 100), 100);
        
        debug('Updating progress UI', { completed, totalTracks, percentage });
        
        // Update progress bar
        if (progressBar && !progressBar.classList.contains('completed')) {
            progressBar.style.setProperty('width', percentage + '%', 'important');
            progressBar.style.setProperty('animation', 'none', 'important');
            progressBar.style.setProperty('transition', 'width 0.3s ease', 'important');
            debug('Progress bar updated', { width: progressBar.style.width, percentage });
        }
        
        // Update main text
        if (loadingText) {
            const trackWord = totalTracks === 1 ? 'traccia' : 'tracce';
            const newText = `Scaricamento ${completed}/${totalTracks} ${trackWord} (${percentage}%)`;
            loadingText.innerHTML = newText;
            debug('Loading text updated', { newText });
        }
        
        // Update active downloads info
        if (activeDownloads) {
            if (active.length === 0 && completed < totalTracks) {
                activeDownloads.innerHTML = '<span class="text-yellow-400">⏳ Preparazione download...</span>';
            } else if (active.length > 0) {
                const activeList = active.slice(0, 2).map(track => {
                    const maxLen = 35;
                    const truncated = track.length > maxLen ? track.substring(0, maxLen) + '...' : track;
                    return `<div class="flex items-center gap-2 mb-1">
                        <span class="text-green-400 animate-pulse">⬇️</span>
                        <span class="text-xs">${truncated}</span>
                    </div>`;
                });
                
                let infoHtml = '<div class="border-t border-gray-600 pt-2 mt-2">' +
                    '<div class="text-xs font-semibold text-blue-400 mb-1">📥 Download attivi:</div>' + 
                    activeList.join('');
                
                if (active.length > 2) {
                    infoHtml += `<div class="text-xs text-gray-500">... e altri ${active.length - 2} download</div>`;
                }
                infoHtml += '</div>';
                activeDownloads.innerHTML = infoHtml;
            } else if (completed >= totalTracks) {
                activeDownloads.innerHTML = '<span class="text-blue-400">📦 Creazione archivio ZIP...</span>';
            }
        }
    };
    
    let pollCount = 0;
    const maxPolls = 300; // Stop after 5 minutes (300 * 1000ms)
    
    const pollFn = () => {
        if (pollCount++ > maxPolls) {
            debug('Progress polling timeout', { jobId });
            return;
        }
        
        const url = ((window.API_URL || 'api.php') + `?action=job_status&job_id=${encodeURIComponent(jobId)}`);
        debug('Fetching job status', { url });
        fetch(url)
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                debug('Job progress update', { jobId, data });
                
                if (!data || typeof data !== 'object') {
                    debug('Invalid job_status response', { data });
                    throw new Error('Invalid response format');
                }
                
                const job = data.job || {};
                const active = data.active || [];
                const completed = parseInt(job.completed || 0, 10);
                
                debug('Progress values', { completed, totalTracks, active: active.length, job });
                
                // Force update even if values seem unchanged
                updateProgress(completed, active);
            })
            .catch(err => {
                debug('Progress polling error', { jobId, error: err.message, errorType: err.constructor.name });
                
                // Try a simple test to see if the API endpoint works at all
                fetch(((window.API_URL || 'api.php') + '?action=active_downloads'))
                    .then(r => r.json())
                    .then(data => {
                        debug('Fallback active_downloads works', data);
                        if (activeDownloads) {
                            const list = (data && Array.isArray(data.active)) ? data.active : [];
                            if (list.length === 0) {
                                activeDownloads.innerHTML = '<span class="text-gray-400">⏳ Preparazione...</span>';
                            } else {
                                activeDownloads.innerHTML = '<span class="text-green-400">⬇️ ' + list.slice(0,2).join(', ') + 
                                    (list.length > 2 ? '...' : '') + '</span>';
                            }
                        }
                    })
                    .catch(fallbackErr => {
                        debug('Even fallback API failed', { error: fallbackErr.message });
                    });
            });
    };
    
    // Initial call - force UI to 0%
    if (progressBar) {
        progressBar.style.width = '0%';
        progressBar.style.animation = 'none';
    }
    if (loadingText) {
        const trackWord = totalTracks === 1 ? 'traccia' : 'tracce';
        loadingText.innerHTML = `Preparando ${totalTracks} ${trackWord}...`;
    }
    
    // Start polling
    pollFn();
    
    // Set up interval
    return setInterval(pollFn, 1000);
}

function startSimpleProgressPolling(progressContainer, totalTracks) {
    const progressBar = progressContainer ? progressContainer.querySelector('.loading-bar') : null;
    const activeDownloads = progressContainer ? progressContainer.querySelector('#activeDownloads') : null;
    const loadingText = progressContainer ? progressContainer.querySelector('.loading-text') : null;
    
    if (!progressContainer) return null;
    
    let completedTracks = 0;
    let lastActiveCount = 0;
    let estimatedCompleted = 0;
    
    const updateProgress = (activeList) => {
        // Simple progress estimation based on active downloads
        if (activeList.length > lastActiveCount) {
            // More downloads started, estimate progress
            estimatedCompleted = Math.min(estimatedCompleted + 0.5, totalTracks - 1);
        } else if (activeList.length < lastActiveCount && lastActiveCount > 0) {
            // Downloads finished, increment completed count
            estimatedCompleted = Math.min(estimatedCompleted + 1, totalTracks);
        } else if (activeList.length === 0 && estimatedCompleted > 0) {
            // No active downloads but we had some, likely finishing
            estimatedCompleted = Math.min(estimatedCompleted + 0.8, totalTracks);
        }
        
        lastActiveCount = activeList.length;
        completedTracks = Math.floor(estimatedCompleted);
        const percentage = Math.min(Math.floor((estimatedCompleted / totalTracks) * 100), 95);
        
        debug('Simple progress update', { activeCount: activeList.length, estimatedCompleted, completedTracks, percentage });
        
        // Update progress bar
        if (progressBar && !progressBar.classList.contains('completed')) {
            progressBar.style.setProperty('width', percentage + '%', 'important');
            progressBar.style.setProperty('animation', 'none', 'important');
        }
        
        // Update main text
        if (loadingText) {
            const trackWord = totalTracks === 1 ? 'traccia' : 'tracce';
            const newText = `Scaricamento ${completedTracks}/${totalTracks} ${trackWord} (${percentage}%)`;
            loadingText.innerHTML = newText;
        }
        
        // Update active downloads info
        if (activeDownloads) {
            if (activeList.length === 0 && estimatedCompleted < totalTracks) {
                activeDownloads.innerHTML = '<span class="text-yellow-400">⏳ Preparazione download...</span>';
            } else if (activeList.length > 0) {
                const displayList = activeList.slice(0, 2).map(track => {
                    const maxLen = 35;
                    const truncated = track.length > maxLen ? track.substring(0, maxLen) + '...' : track;
                    return `<div class="flex items-center gap-2 mb-1">
                        <span class="text-green-400 animate-pulse">⬇️</span>
                        <span class="text-xs">${truncated}</span>
                    </div>`;
                });
                
                let infoHtml = '<div class="border-t border-gray-600 pt-2 mt-2">' +
                    '<div class="text-xs font-semibold text-blue-400 mb-1">📥 Download attivi:</div>' + 
                    displayList.join('');
                
                if (activeList.length > 2) {
                    infoHtml += `<div class="text-xs text-gray-500">... e altri ${activeList.length - 2} download</div>`;
                }
                infoHtml += '</div>';
                activeDownloads.innerHTML = infoHtml;
            } else if (estimatedCompleted >= totalTracks * 0.9) {
                activeDownloads.innerHTML = '<span class="text-blue-400">📦 Creazione archivio ZIP...</span>';
            }
        }
    };
    
    const pollFn = () => {
        fetch(((window.API_URL || 'api.php') + '?action=active_downloads'))
            .then(r => r.json())
            .then(data => {
                const list = (data && Array.isArray(data.active)) ? data.active : [];
                updateProgress(list);
            })
            .catch(err => {
                debug('Simple polling error', { error: err.message });
                if (activeDownloads) {
                    activeDownloads.innerHTML = '<span class="text-red-400">⚠️ Errore nel monitoraggio</span>';
                }
            });
    };
    
    // Initialize
    if (progressBar) {
        progressBar.style.width = '0%';
        progressBar.style.animation = 'none';
    }
    if (loadingText) {
        const trackWord = totalTracks === 1 ? 'traccia' : 'tracce';
        loadingText.innerHTML = `Preparando ${totalTracks} ${trackWord}...`;
    }
    
    // Start polling
    pollFn();
    return setInterval(pollFn, 1000);
}

function startTimeBasedProgress(progressContainer, totalTracks) {
    const progressBar = progressContainer ? progressContainer.querySelector('.loading-bar') : null;
    const activeDownloads = progressContainer ? progressContainer.querySelector('#activeDownloads') : null;
    const loadingText = progressContainer ? progressContainer.querySelector('.loading-text') : null;
    
    if (!progressContainer) return null;
    
    // Time-based progress simulation
    let startTime = Date.now();
    let completedTracks = 0;
    let estimatedTimePerTrack = 15000; // 15 seconds per track on average
    let totalEstimatedTime = totalTracks * estimatedTimePerTrack;
    let currentTrackNames = [
        "Preparazione download...",
        "Connessione al servizio...",
        "Download traccia 1...",
        "Download traccia 2...", 
        "Download traccia 3...",
        "Download traccia 4...",
        "Download traccia 5...",
        "Finalizzazione..."
    ];
    
    const updateProgress = () => {
        const elapsed = Date.now() - startTime;
        const timeProgress = Math.min(elapsed / totalEstimatedTime, 0.95);
        
        // Calculate completed tracks based on time
        completedTracks = Math.floor(timeProgress * totalTracks);
        const percentage = Math.floor(timeProgress * 100);
        
        debug('Time-based progress update', { elapsed, timeProgress, completedTracks, percentage, totalTracks });
        
        // Update progress bar
        if (progressBar && !progressBar.classList.contains('completed')) {
            progressBar.style.setProperty('width', percentage + '%', 'important');
            progressBar.style.setProperty('animation', 'none', 'important');
            progressBar.style.setProperty('transition', 'width 0.5s ease', 'important');
        }
        
        // Update main text
        if (loadingText) {
            const trackWord = totalTracks === 1 ? 'traccia' : 'tracce';
            const newText = `Scaricamento ${completedTracks}/${totalTracks} ${trackWord} (${percentage}%)`;
            loadingText.innerHTML = newText;
        }
        
        // Update active downloads info with simulated track names
        if (activeDownloads) {
            const currentPhase = Math.floor((elapsed / totalEstimatedTime) * 8); // 8 phases
            const phaseName = currentTrackNames[Math.min(currentPhase, currentTrackNames.length - 1)];
            
            if (percentage < 5) {
                activeDownloads.innerHTML = '<span class="text-yellow-400">⏳ Inizializzazione download...</span>';
            } else if (percentage < 90) {
                // Show simulated active downloads
                const simulatedTracks = [];
                for (let i = 0; i < Math.min(3, totalTracks - completedTracks); i++) {
                    const trackNum = completedTracks + i + 1;
                    if (trackNum <= totalTracks) {
                        simulatedTracks.push(`Traccia ${trackNum} - Download in corso`);
                    }
                }
                
                if (simulatedTracks.length > 0) {
                    const displayList = simulatedTracks.map(track => {
                        return `<div class="flex items-center gap-2 mb-1">
                            <span class="text-green-400 animate-pulse">⬇️</span>
                            <span class="text-xs">${track}</span>
                        </div>`;
                    });
                    
                    let infoHtml = '<div class="border-t border-gray-600 pt-2 mt-2">' +
                        '<div class="text-xs font-semibold text-blue-400 mb-1">📥 Download attivi:</div>' + 
                        displayList.join('') + '</div>';
                    
                    activeDownloads.innerHTML = infoHtml;
                } else {
                    activeDownloads.innerHTML = '<span class="text-blue-400">📦 Preparazione archivio...</span>';
                }
            } else {
                activeDownloads.innerHTML = '<span class="text-blue-400">📦 Creazione archivio ZIP...</span>';
            }
        }
        
        // Return true if we should continue polling
        return timeProgress < 0.95;
    };
    
    // Initialize
    if (progressBar) {
        progressBar.style.width = '0%';
        progressBar.style.animation = 'none';
    }
    if (loadingText) {
        const trackWord = totalTracks === 1 ? 'traccia' : 'tracce';
        loadingText.innerHTML = `Preparando ${totalTracks} ${trackWord}...`;
    }
    
    // Start progress updates
    const intervalId = setInterval(() => {
        const shouldContinue = updateProgress();
        if (!shouldContinue) {
            debug('Time-based progress completed, stopping updates');
            // Don't clear interval here, let the main download completion handle it
        }
    }, 500); // Update every 500ms for smooth progress
    
    // Initial update
    updateProgress();
    
    return intervalId;
}

function startRealisticProgress(progressContainer) {
    const progressBar = progressContainer.querySelector('.loading-bar');
    if (!progressBar) return;
    
    let currentProgress = 0;
    const targetProgress = 95; // Don't reach 100% until completion
    const progressSteps = [
        { time: 0, progress: 0 },
        { time: 500, progress: 12 },
        { time: 1000, progress: 28 },
        { time: 2000, progress: 45 },
        { time: 3500, progress: 62 },
        { time: 5000, progress: 78 },
        { time: 7000, progress: 88 },
        { time: 10000, progress: 95 }
    ];
    
    let currentStep = 0;
    
    function updateProgress() {
        if (currentStep < progressSteps.length) {
            const step = progressSteps[currentStep];
            
            setTimeout(() => {
                if (progressBar && !progressBar.classList.contains('completed')) {
                    progressBar.style.width = step.progress + '%';
                    currentStep++;
                    updateProgress();
                }
            }, currentStep === 0 ? 0 : progressSteps[currentStep].time - progressSteps[currentStep - 1].time);
        }
    }
    
    updateProgress();
}

function completeInlineProgressBar(progressBar) {
    if (!progressBar) return;
    
    const loadingBar = progressBar.querySelector('.loading-bar');
    const loadingText = progressBar.querySelector('.loading-text');
    
    if (loadingBar) {
        // Remove any existing animations and set to 100%
        loadingBar.classList.add('completed');
        loadingBar.style.width = '100%';
        loadingBar.style.animation = 'none';
    }
    
    if (loadingText) {
        loadingText.innerHTML = 'Download completato! <i class="fas fa-check text-accent ml-2"></i>';
    }
}

function removeInlineProgressBar(progressBar) {
    if (progressBar && progressBar.parentNode) {
        progressBar.style.opacity = '0';
        progressBar.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            if (progressBar.parentNode) {
                progressBar.remove();
            }
        }, 300);
    }
}

// Burger Menu Functions
function initBurgerMenu() {
    const burgerMenu = document.getElementById('burgerMenu');
    const mobileNav = document.getElementById('mobileNav');
    
    if (burgerMenu && mobileNav) {
        burgerMenu.addEventListener('click', function() {
            burgerMenu.classList.toggle('open');
            mobileNav.classList.toggle('hidden');
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!burgerMenu.contains(e.target) && !mobileNav.contains(e.target)) {
                burgerMenu.classList.remove('open');
                mobileNav.classList.add('hidden');
            }
        });
    }
}

// Service Selection Functions
function initServiceSelection() {
    const serviceLabels = document.querySelectorAll('.service-label');
    
    serviceLabels.forEach(label => {
        label.addEventListener('click', function() {
            // Remove active state from all services
            serviceLabels.forEach(l => {
                l.classList.remove('active');
                const content = l.querySelector('.service-content');
                if (content) {
                    content.removeAttribute('style');
                }
            });
            
            // Add active state to clicked service
            this.classList.add('active');
            
            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        });
    });
    
    // Clean all inline styles first and set initial active state
    setTimeout(() => {
        // Force clear ALL inline styles
        const allLabels = document.querySelectorAll('.service-label');
        allLabels.forEach(l => {
            const content = l.querySelector('.service-content');
            if (content) {
                content.removeAttribute('style');
            }
            l.classList.remove('active');
        });
        
        // Then set active state for checked service only
        const checkedService = document.querySelector('input[name="service"]:checked');
        if (checkedService) {
            const label = checkedService.closest('.service-label');
            if (label) {
                label.classList.add('active');
            }
        }
    }, 200);
}

// Enhanced Button Loading States
function setButtonLoading(button, loading = true) {
    if (loading) {
        button.disabled = true;
        const span = button.querySelector('span');
        if (span) {
            span.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Caricamento...';
        }
        button.classList.add('loading');
    } else {
        button.disabled = false;
        const span = button.querySelector('span');
        if (span) {
            span.innerHTML = '<i class="fas fa-search mr-2"></i> Recupera';
        }
        button.classList.remove('loading');
    }
}

// Simulate progress for demo
function simulateProgress() {
    const progressBars = document.querySelectorAll('.bg-green-600.h-2');
    progressBars.forEach(bar => {
        let width = 0;
        const interval = setInterval(() => {
            width += 1;
            bar.style.width = `${width}%`;
            if (width >= 100) {
                clearInterval(interval);
            }
        }, 50);
    });
}

// Responsive Validation Management
function initResponsiveValidation() {
    const desktopInput = document.getElementById('spotify_url');
    const mobileInput = document.getElementById('spotify_url_mobile');
    
    if (!desktopInput || !mobileInput) return;
    
    function updateValidation() {
        const isDesktop = window.innerWidth >= 768;
        
        if (isDesktop) {
            // Desktop: require desktop input, remove required from mobile
            desktopInput.setAttribute('required', '');
            mobileInput.removeAttribute('required');
        } else {
            // Mobile: require mobile input, remove required from desktop
            mobileInput.setAttribute('required', '');
            desktopInput.removeAttribute('required');
        }
    }
    
    // Set initial state
    updateValidation();
    
    // Update on resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateValidation, 100);
    });
}
