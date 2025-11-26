// Nome e versione della cache
const CACHE_NAME = 'regex-machina-cache-v1';

// Lista di file da mettere in cache (l' "app shell")
const urlsToCache = [
    '.', // Alias per index.html
    'index.html',
    'css/style.css',
    'css/prism.css',
    'js/app.js',
    'js/prism.js',
    'images/icon-192.png',
    'images/icon-512.png',
    // Aggiungi qui altre risorse statiche se ne hai, come i file dei font
    'https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css'
];

// Evento 'install': viene eseguito quando il service worker viene installato.
self.addEventListener('install', event => {
    // Aspetta che l'installazione finisca prima di procedere
    event.waitUntil(
        // Apri la cache con il nome che abbiamo definito
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Cache aperta');
                // Aggiungi tutti i file della nostra app shell alla cache
                return cache.addAll(urlsToCache);
            })
    );
});

// Evento 'fetch': si attiva ogni volta che l'app fa una richiesta di rete (es. per un file CSS, un'immagine, o una chiamata API).
self.addEventListener('fetch', event => {
    event.respondWith(
        // Cerca la risorsa richiesta nella cache
        caches.match(event.request)
            .then(response => {
                // Se la risorsa è nella cache, restituiscila
                if (response) {
                    return response;
                }
                
                // Altrimenti, fai la richiesta di rete originale
                return fetch(event.request);
            }
        )
    );
});

// Evento 'activate': si attiva quando il service worker diventa attivo.
// Utile per pulire le vecchie cache.
self.addEventListener('activate', event => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});