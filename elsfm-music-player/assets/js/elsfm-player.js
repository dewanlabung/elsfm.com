/**
 * ELSFM Music Player
 *
 * Handles audio playback, queue management, and interactive search
 * for the ELSFM WordPress plugin.
 */
(function () {
    'use strict';

    var config = window.elsfmConfig || {};
    var apiUrl = (config.apiUrl || '').replace(/\/+$/, '');
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------
    var audio = null;
    var currentTrack = null;
    var queue = [];
    var queueIndex = -1;
    var isPlaying = false;
    var isSeeking = false;
    var playerBar = null;

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------
    function formatTime(ms) {
        var totalSec = Math.floor(ms / 1000);
        var m = Math.floor(totalSec / 60);
        var s = totalSec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    // -------------------------------------------------------------------------
    // Persistent bottom player bar (created on first play)
    // -------------------------------------------------------------------------
    function ensurePlayerBar() {
        if (playerBar) return;

        var bar = document.createElement('div');
        bar.id = 'elsfm-player-bar';
        bar.className = 'elsfm-player-bar';
        bar.innerHTML =
            '<div class="elsfm-pb-progress-wrap">' +
                '<div class="elsfm-pb-progress" id="elsfm-pb-progress"></div>' +
            '</div>' +
            '<div class="elsfm-pb-inner">' +
                '<div class="elsfm-pb-left">' +
                    '<div class="elsfm-pb-art" id="elsfm-pb-art"></div>' +
                    '<div class="elsfm-pb-info">' +
                        '<div class="elsfm-pb-title" id="elsfm-pb-title"></div>' +
                        '<div class="elsfm-pb-artist" id="elsfm-pb-artist"></div>' +
                    '</div>' +
                '</div>' +
                '<div class="elsfm-pb-controls">' +
                    '<button class="elsfm-pb-btn" id="elsfm-pb-prev" aria-label="Previous">' +
                        '<svg viewBox="0 0 24 24" width="20" height="20"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z" fill="currentColor"/></svg>' +
                    '</button>' +
                    '<button class="elsfm-pb-btn elsfm-pb-btn--play" id="elsfm-pb-play" aria-label="Play">' +
                        '<svg viewBox="0 0 24 24" width="28" height="28" class="elsfm-icon-play"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg>' +
                        '<svg viewBox="0 0 24 24" width="28" height="28" class="elsfm-icon-pause" style="display:none"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" fill="currentColor"/></svg>' +
                    '</button>' +
                    '<button class="elsfm-pb-btn" id="elsfm-pb-next" aria-label="Next">' +
                        '<svg viewBox="0 0 24 24" width="20" height="20"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z" fill="currentColor"/></svg>' +
                    '</button>' +
                '</div>' +
                '<div class="elsfm-pb-right">' +
                    '<span class="elsfm-pb-time" id="elsfm-pb-time">0:00 / 0:00</span>' +
                    '<div class="elsfm-pb-volume-wrap">' +
                        '<button class="elsfm-pb-btn" id="elsfm-pb-vol-btn" aria-label="Volume">' +
                            '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z" fill="currentColor"/></svg>' +
                        '</button>' +
                        '<input type="range" min="0" max="100" value="80" class="elsfm-pb-volume" id="elsfm-pb-volume" />' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(bar);
        document.body.classList.add('elsfm-has-player');
        playerBar = bar;

        // Progress bar seeking
        var progressWrap = qs('.elsfm-pb-progress-wrap', bar);
        progressWrap.addEventListener('mousedown', function (e) {
            isSeeking = true;
            seekTo(e, progressWrap);
        });
        document.addEventListener('mousemove', function (e) {
            if (isSeeking) seekTo(e, progressWrap);
        });
        document.addEventListener('mouseup', function () {
            isSeeking = false;
        });

        // Controls
        qs('#elsfm-pb-play').addEventListener('click', togglePlay);
        qs('#elsfm-pb-prev').addEventListener('click', playPrev);
        qs('#elsfm-pb-next').addEventListener('click', playNext);
        qs('#elsfm-pb-volume').addEventListener('input', function () {
            if (audio) audio.volume = this.value / 100;
        });
    }

    function seekTo(e, wrap) {
        if (!audio || !audio.duration) return;
        var rect = wrap.getBoundingClientRect();
        var pct = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        audio.currentTime = pct * audio.duration;
    }

    function updatePlayerBarUI() {
        if (!playerBar || !currentTrack) return;

        qs('#elsfm-pb-title').textContent = currentTrack.name || '';
        qs('#elsfm-pb-artist').textContent = currentTrack.artist || '';

        var artEl = qs('#elsfm-pb-art');
        if (currentTrack.image) {
            artEl.innerHTML = '<img src="' + currentTrack.image + '" alt="" />';
        } else {
            artEl.innerHTML = '<svg viewBox="0 0 24 24" width="40" height="40"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" fill="currentColor"/></svg>';
        }
    }

    function updatePlayPauseIcon() {
        if (!playerBar) return;
        var playIcon = qs('.elsfm-icon-play', playerBar);
        var pauseIcon = qs('.elsfm-icon-pause', playerBar);
        if (isPlaying) {
            playIcon.style.display = 'none';
            pauseIcon.style.display = '';
        } else {
            playIcon.style.display = '';
            pauseIcon.style.display = 'none';
        }
    }

    // -------------------------------------------------------------------------
    // Audio engine
    // -------------------------------------------------------------------------
    function initAudio() {
        if (audio) return;
        audio = new Audio();
        audio.volume = 0.8;

        audio.addEventListener('timeupdate', function () {
            if (!playerBar || isSeeking) return;
            var dur = audio.duration || 0;
            var cur = audio.currentTime || 0;
            var pct = dur > 0 ? (cur / dur) * 100 : 0;
            qs('#elsfm-pb-progress').style.width = pct + '%';
            qs('#elsfm-pb-time').textContent = formatTime(cur * 1000) + ' / ' + formatTime(dur * 1000);
        });

        audio.addEventListener('ended', function () {
            playNext();
        });

        audio.addEventListener('error', function () {
            isPlaying = false;
            updatePlayPauseIcon();
            highlightActiveRow();
        });
    }

    function playTrack(trackData) {
        initAudio();
        ensurePlayerBar();

        currentTrack = trackData;
        var trackId = trackData.id;

        // The download/stream URL
        var src = apiUrl + '/api/v1/tracks/' + trackId + '/download';

        audio.src = src;
        audio.play().then(function () {
            isPlaying = true;
            updatePlayPauseIcon();
            updatePlayerBarUI();
            highlightActiveRow();
        }).catch(function () {
            // Autoplay blocked — user needs to interact
            isPlaying = false;
            updatePlayPauseIcon();
            updatePlayerBarUI();
            highlightActiveRow();
        });
    }

    function togglePlay() {
        if (!audio) return;
        if (isPlaying) {
            audio.pause();
            isPlaying = false;
        } else {
            audio.play().catch(function () {});
            isPlaying = true;
        }
        updatePlayPauseIcon();
        highlightActiveRow();
    }

    function playNext() {
        if (queue.length === 0) return;
        queueIndex = (queueIndex + 1) % queue.length;
        playTrack(queue[queueIndex]);
    }

    function playPrev() {
        if (queue.length === 0) return;
        queueIndex = (queueIndex - 1 + queue.length) % queue.length;
        playTrack(queue[queueIndex]);
    }

    // -------------------------------------------------------------------------
    // Highlight active track row
    // -------------------------------------------------------------------------
    function highlightActiveRow() {
        qsa('.elsfm-track-row').forEach(function (row) {
            var rowId = row.getAttribute('data-track-id');
            if (currentTrack && String(rowId) === String(currentTrack.id)) {
                row.classList.toggle('elsfm-track-row--playing', isPlaying);
                row.classList.toggle('elsfm-track-row--active', true);
            } else {
                row.classList.remove('elsfm-track-row--playing', 'elsfm-track-row--active');
            }
        });
    }

    // -------------------------------------------------------------------------
    // Extract track data from a DOM element
    // -------------------------------------------------------------------------
    function getTrackDataFromEl(el) {
        return {
            id: el.getAttribute('data-track-id'),
            name: el.getAttribute('data-track-name') || '',
            artist: el.getAttribute('data-track-artist') || '',
            image: el.getAttribute('data-track-image') || '',
            duration: parseInt(el.getAttribute('data-track-duration') || '0', 10)
        };
    }

    // -------------------------------------------------------------------------
    // Build queue from sibling track rows
    // -------------------------------------------------------------------------
    function buildQueueFromContext(clickedRow) {
        var parent = clickedRow.parentElement;
        var rows = qsa('.elsfm-track-row', parent);
        queue = [];
        queueIndex = 0;

        rows.forEach(function (row, idx) {
            queue.push(getTrackDataFromEl(row));
            if (row === clickedRow) {
                queueIndex = idx;
            }
        });
    }

    // -------------------------------------------------------------------------
    // Click delegation
    // -------------------------------------------------------------------------
    document.addEventListener('click', function (e) {
        // Play button inside a track row
        var playBtn = e.target.closest('.elsfm-play-btn');
        if (playBtn) {
            var row = playBtn.closest('.elsfm-track-row') || playBtn.closest('.elsfm-single-track');
            if (row) {
                e.preventDefault();
                var trackData = getTrackDataFromEl(row);

                // If clicking the same track, toggle play/pause
                if (currentTrack && String(currentTrack.id) === String(trackData.id)) {
                    togglePlay();
                    return;
                }

                // Build queue from sibling rows if in a tracklist
                var trackRow = playBtn.closest('.elsfm-track-row');
                if (trackRow) {
                    buildQueueFromContext(trackRow);
                } else {
                    queue = [trackData];
                    queueIndex = 0;
                }

                playTrack(trackData);
            }
            return;
        }

        // Grid item play overlay
        var overlay = e.target.closest('.elsfm-play-overlay');
        if (overlay) {
            var gridItem = overlay.closest('.elsfm-grid-item--playable');
            if (gridItem) {
                e.preventDefault();
                var td = getTrackDataFromEl(gridItem);
                queue = [td];
                queueIndex = 0;
                playTrack(td);
            }
            return;
        }
    });

    // -------------------------------------------------------------------------
    // Live search form handling
    // -------------------------------------------------------------------------
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-elsfm-search]');
        if (!form) return;
        e.preventDefault();

        var input = qs('.elsfm-search-input', form);
        var query = (input ? input.value : '').trim();
        if (!query) return;

        var resultsContainer = form.nextElementSibling || qs('[data-elsfm-search-results]');
        if (!resultsContainer) return;

        resultsContainer.innerHTML = '<div class="elsfm-loading"></div>';

        var url = ajaxUrl +
            '?action=elsfm_api_proxy' +
            '&nonce=' + encodeURIComponent(nonce) +
            '&endpoint=search' +
            '&params=' + encodeURIComponent(JSON.stringify({ query: query, limit: 10 }));

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.success || !resp.data || !resp.data.results) {
                    resultsContainer.innerHTML = '<p class="elsfm-no-results">No results found.</p>';
                    return;
                }
                renderSearchResults(resp.data.results, resultsContainer);
            })
            .catch(function () {
                resultsContainer.innerHTML = '<p class="elsfm-error">Search failed. Please try again.</p>';
            });
    });

    function renderSearchResults(results, container) {
        var html = '';

        if (results.tracks && results.tracks.data && results.tracks.data.length) {
            html += '<div class="elsfm-section"><h4>Tracks</h4><div class="elsfm-tracklist">';
            results.tracks.data.forEach(function (track, i) {
                html += buildTrackRowHtml(track, i);
            });
            html += '</div></div>';
        }

        if (results.albums && results.albums.data && results.albums.data.length) {
            html += '<div class="elsfm-section"><h4>Albums</h4><div class="elsfm-grid">';
            results.albums.data.forEach(function (album) {
                var img = resolveImage(album.image);
                html += '<div class="elsfm-grid-item">' +
                    '<div class="elsfm-grid-img">' + (img ? '<img src="' + escAttr(img) + '" alt="" loading="lazy" />' : '') + '</div>' +
                    '<p class="elsfm-grid-title">' + escHtml(album.name || '') + '</p>' +
                    '</div>';
            });
            html += '</div></div>';
        }

        if (results.artists && results.artists.data && results.artists.data.length) {
            html += '<div class="elsfm-section"><h4>Artists</h4><div class="elsfm-grid elsfm-grid--round">';
            results.artists.data.forEach(function (artist) {
                var img = resolveImage(artist.image_small);
                html += '<div class="elsfm-grid-item">' +
                    '<div class="elsfm-grid-img">' + (img ? '<img src="' + escAttr(img) + '" alt="" loading="lazy" />' : '') + '</div>' +
                    '<p class="elsfm-grid-title">' + escHtml(artist.name || '') + '</p>' +
                    '</div>';
            });
            html += '</div></div>';
        }

        if (!html) {
            html = '<p class="elsfm-no-results">No results found.</p>';
        }

        container.innerHTML = html;
    }

    function buildTrackRowHtml(track, index) {
        var id = track.id || 0;
        var name = track.name || '';
        var artists = (track.artists || []).map(function (a) { return a.name; }).join(', ') || 'Unknown';
        var image = resolveImage(track.image);
        var duration = track.duration || 0;
        var plays = track.plays ? Number(track.plays).toLocaleString() : '0';

        return '<div class="elsfm-track-row"' +
            ' data-track-id="' + id + '"' +
            ' data-track-name="' + escAttr(name) + '"' +
            ' data-track-artist="' + escAttr(artists) + '"' +
            ' data-track-image="' + escAttr(image) + '"' +
            ' data-track-duration="' + duration + '">' +
            '<div class="elsfm-track-num">' + (index + 1) + '</div>' +
            '<div class="elsfm-track-img">' + (image ? '<img src="' + escAttr(image) + '" alt="" class="elsfm-track-thumb" loading="lazy" />' : '') + '</div>' +
            '<div class="elsfm-track-info">' +
                '<span class="elsfm-track-name">' + escHtml(name) + '</span>' +
                '<span class="elsfm-track-artist">' + escHtml(artists) + '</span>' +
            '</div>' +
            '<div class="elsfm-track-plays">' + plays + '</div>' +
            '<div class="elsfm-track-duration">' + formatTime(duration) + '</div>' +
            '<button class="elsfm-play-btn" aria-label="Play">' +
                '<svg viewBox="0 0 24 24" width="20" height="20"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg>' +
            '</button>' +
            '</div>';
    }

    function resolveImage(img) {
        if (!img) return '';
        if (img.indexOf('http') === 0) return img;
        return apiUrl + '/' + img.replace(/^\//, '');
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // -------------------------------------------------------------------------
    // Keyboard shortcuts
    // -------------------------------------------------------------------------
    document.addEventListener('keydown', function (e) {
        // Only when not typing in an input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;

        if (e.code === 'Space' && audio) {
            e.preventDefault();
            togglePlay();
        }
    });

})();
