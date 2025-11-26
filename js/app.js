document.addEventListener('DOMContentLoaded', () => {

    // --- 1. SETUP INIZIALE ---
    const canvas = document.getElementById('automaton-canvas');
    const ctx = canvas.getContext('2d');
    const toastContainer = document.getElementById('toast-container');
    const saveButton = document.getElementById('save-button');
    const savedElementsButton = document.getElementById('saved-elements-button');
    const exportPngButton = document.getElementById('export-png-button');
    const clearButton = document.getElementById('clear-button');
    const shareLinkContainer = document.getElementById('share-link-container');
    const shareLinkInput = document.getElementById('share-link-input');
    const testStringInput = document.getElementById('test-string-input');
    const testButton = document.getElementById('test-button');
    const generateStringsButton = document.getElementById('generate-strings-button');
    const analyzeStructureButton = document.getElementById('analyze-structure-button');
    const languageAnalysisOutput = document.getElementById('language-analysis-output');
    const languageDescription = document.getElementById('language-description');
    const generatedStringsList = document.getElementById('generated-strings-list');
    const convertButton = document.getElementById('convert-button');
    const minimizeButton = document.getElementById('minimize-button');
    const generateCodeButton = document.getElementById('generate-code-button');
    const codeLanguageSelect = document.getElementById('code-language-select');
    const modalContainer = document.getElementById('modal-container');
    const modalCloseButton = document.getElementById('modal-close-button');
    const generatedCodeBlock = document.getElementById('generated-code-block');
    const copyCodeButton = document.getElementById('copy-code-button');
    const savedModalContainer = document.getElementById('saved-modal-container');
    const savedModalCloseButton = document.getElementById('saved-modal-close-button');
    const savedAutomataList = document.getElementById('saved-automata-list');
    const regexInput = document.getElementById('regex-input');
    const regexToNfaButton = document.getElementById('regex-to-nfa-button');
    const resultOutput = document.getElementById('result-output');
    const simControlsContainer = document.getElementById('simulation-controls');
    const playPauseButton = document.getElementById('play-pause-button');
    const nextStepButton = document.getElementById('next-step-button');
    const resetSimButton = document.getElementById('reset-sim-button');
    const simStatus = document.getElementById('simulation-status');

    let automaton = { states: [], transitions: [] };
    let stateCounter = 0;
    let interaction = {
        isDragging: false,
        dragTarget: null,
        startPos: { x: 0, y: 0 },
        offset: { x: 0, y: 0 },
        currentPos: { x: 0, y: 0 },
        longPressTimer: null,
        hasMoved: false
    };
    let simulation = {
        active: false, history: [], currentStep: 0, isPlaying: false,
        intervalId: null, activeStates: []
    };
    let analysis = {
        active: false,
        unreachable: [],
        dead: []
    };

    function resizeCanvas() {
        const container = document.getElementById('canvas-container');
        const dpr = window.devicePixelRatio || 1;
        canvas.width = container.clientWidth * dpr;
        canvas.height = container.clientHeight * dpr;
        ctx.scale(dpr, dpr);
        draw();
    }

    // --- FUNZIONI DI UTILITY E GESTIONE STATO ---
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => { toast.remove(); }, 5000);
    }
    function resetApplicationState() {
        automaton = { states: [], transitions: [] };
        stateCounter = 0;
        resetSimulation();
        testStringInput.value = '';
        regexInput.value = '';
        resultOutput.innerHTML = '<p>Detailed test results will appear here.</p>';
        languageAnalysisOutput.classList.add('hidden');
        shareLinkContainer.classList.add('hidden');
        shareLinkInput.value = '';
        if (window.history.pushState) {
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({path: newUrl}, '', newUrl);
        }
        draw();
        showToast('Clean canvas. Ready for a new project!', 'info');
    }
    function loadAutomatonFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const shareKey = urlParams.get('load');
        if (shareKey) {
            showToast('Loading shared automaton...', 'info');
            fetch(`api/load.php?key=${shareKey}`)
                .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
                .then(loadedAutomaton => {
                    automaton = loadedAutomaton;
                    stateCounter = loadedAutomaton.states.length > 0 ? Math.max(...loadedAutomaton.states.map(s => s.id)) + 1 : 0;
                    draw();
                    showToast('Automaton loaded successfully!', 'success');
                })
                .catch(error => { showToast(error.message, 'error'); });
        }
    }

    // --- 2. FUNZIONI DI DISEGNO ---
    function draw() {
        const dpr = window.devicePixelRatio || 1;
        ctx.fillStyle = '#2d3748';
        ctx.fillRect(0, 0, canvas.width / dpr, canvas.height / dpr);
        
        if (interaction.isDragging && interaction.dragTarget) {
            drawPreviewLine();
        }

        automaton.transitions.forEach(t => drawTransition(t));
        automaton.states.forEach(s => drawState(s));
    }
    function drawState(state) {
        ctx.beginPath();
        ctx.arc(state.x, state.y, state.radius, 0, 2 * Math.PI);
        ctx.strokeStyle = '#e2e8f0'; ctx.lineWidth = 2;
        if (simulation.active && simulation.activeStates.includes(state.id)) {
            ctx.fillStyle = '#f6e05e'; ctx.shadowColor = '#f6e05e'; ctx.shadowBlur = 15;
        } else if (analysis.active && analysis.unreachable.includes(state.id)) {
            ctx.fillStyle = '#f56565'; ctx.strokeStyle = '#e2e8f0';
            ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0;
        } else if (analysis.active && analysis.dead.includes(state.id)) {
            ctx.fillStyle = '#1a202c';
            ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0;
        } else {
            ctx.fillStyle = '#2d3748';
            ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0;
        }
        ctx.fill(); ctx.stroke();
        ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0;
        if (state.isFinal) {
            ctx.beginPath();
            ctx.arc(state.x, state.y, state.radius - 6, 0, 2 * Math.PI);
            ctx.strokeStyle = '#a0aec0'; ctx.lineWidth = 2; ctx.stroke();
        }
        if (state.isInitial) {
             ctx.beginPath();
             ctx.moveTo(state.x - state.radius - 20, state.y);
             ctx.lineTo(state.x - state.radius, state.y);
             ctx.lineTo(state.x - state.radius - 5, state.y - 5);
             ctx.moveTo(state.x - state.radius, state.y);
             ctx.lineTo(state.x - state.radius - 5, state.y + 5);
             ctx.strokeStyle = '#4299e1'; ctx.lineWidth = 2; ctx.stroke();
        }
        ctx.fillStyle = '#e2e8f0'; ctx.font = '16px "Roboto Mono"';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(state.label, state.x, state.y);
    }
    function drawPreviewLine() {
        const pos = interaction.currentPos;
        const target = interaction.dragTarget;
        if (!pos || !target) return;

        const distance = Math.sqrt((pos.x - target.x)**2 + (pos.y - target.y)**2);
        if (distance > target.radius) {
            ctx.beginPath();
            ctx.moveTo(target.x, target.y);
            ctx.lineTo(pos.x, pos.y);
            ctx.strokeStyle = '#4299e1';
            ctx.lineWidth = 2;
            ctx.setLineDash([5, 5]);
            ctx.stroke();
            ctx.setLineDash([]);
        }
    }
    function drawTransition(transition) {
        const fromState = automaton.states.find(s => s.id === transition.from);
        const toState = automaton.states.find(s => s.id === transition.to);
        if (!fromState || !toState) return;
        ctx.beginPath();
        const angle = Math.atan2(toState.y - fromState.y, toState.x - fromState.x);
        const startX = fromState.x + fromState.radius * Math.cos(angle);
        const startY = fromState.y + fromState.radius * Math.sin(angle);
        const endX = toState.x - toState.radius * Math.cos(angle);
        const endY = toState.y - toState.radius * Math.sin(angle);
        ctx.moveTo(startX, startY);
        if (fromState.id === toState.id) {
            const loopRadius = 25; const loopAngle = Math.PI / 2;
            const controlX1 = fromState.x + loopRadius * Math.cos(angle + loopAngle);
            const controlY1 = fromState.y + loopRadius * Math.sin(angle + loopAngle);
            const controlX2 = fromState.x + loopRadius * Math.cos(angle - loopAngle);
            const controlY2 = fromState.y + loopRadius * Math.sin(angle - loopAngle);
            ctx.bezierCurveTo(controlX1, controlY1, controlX2, controlY2, startX, startY);
        } else { ctx.lineTo(endX, endY); }
        ctx.strokeStyle = '#a0aec0'; ctx.lineWidth = 1.5; ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(endX, endY);
        const arrowSize = 10;
        ctx.lineTo(endX - arrowSize * Math.cos(angle - Math.PI / 6), endY - arrowSize * Math.sin(angle - Math.PI / 6));
        ctx.moveTo(endX, endY);
        ctx.lineTo(endX - arrowSize * Math.cos(angle + Math.PI / 6), endY - arrowSize * Math.sin(angle + Math.PI / 6));
        ctx.strokeStyle = '#a0aec0'; ctx.lineWidth = 1.5; ctx.stroke();
        let labelX = (startX + endX) / 2; let labelY = (startY + endY) / 2 - 10;
        if (fromState.id === toState.id) {
            labelX = fromState.x + (fromState.radius + 15) * Math.cos(angle);
            labelY = fromState.y + (fromState.radius + 15) * Math.sin(angle);
        }
        ctx.fillStyle = '#4299e1'; ctx.font = 'bold 14px "Roboto Mono"'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.save(); ctx.translate(labelX, labelY); ctx.rotate(angle);
        if(Math.abs(angle) > Math.PI / 2) { ctx.rotate(Math.PI); }
        ctx.fillText(transition.label, 0, 0); ctx.restore();
    }
    
    // --- 3. GESTIONE EVENTI UTENTE ---
    function getMousePos(canvas, evt) {
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const clientX = evt.clientX ?? evt.touches[0].clientX;
        const clientY = evt.clientY ?? evt.touches[0].clientY;
        return {
            x: (clientX - rect.left) * (canvas.width / (rect.width * dpr)),
            y: (clientY - rect.top) * (canvas.height / (rect.height * dpr))
        };
    }
    function getElementAt(pos) {
        for (let i = automaton.states.length - 1; i >= 0; i--) {
            const state = automaton.states[i];
            const distance = Math.sqrt((pos.x - state.x)**2 + (pos.y - state.y)**2);
            if (distance < state.radius) return { type: 'state', object: state };
        }
        return null;
    }

    function handleInteractionStart(e) {
        if (simulation.active) return;
        e.preventDefault();
        const pos = getMousePos(canvas, e);
        interaction.startPos = pos;
        interaction.hasMoved = false;
        
        const element = getElementAt(pos);
        if (element && element.type === 'state') {
            interaction.isDragging = true;
            interaction.dragTarget = element.object;
            interaction.offset = {
                x: pos.x - element.object.x,
                y: pos.y - element.object.y
            };
            interaction.longPressTimer = setTimeout(() => {
                if (!interaction.hasMoved) {
                    handleLongPress();
                }
            }, 700);
        }
    }

    function handleInteractionMove(e) {
        if (!interaction.isDragging) return;
        e.preventDefault();
        
        const pos = getMousePos(canvas, e);
        interaction.currentPos = pos;
        
        const dx = pos.x - interaction.startPos.x;
        const dy = pos.y - interaction.startPos.y;
        
        if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
            interaction.hasMoved = true;
            clearTimeout(interaction.longPressTimer);
            interaction.longPressTimer = null;
        }

        if (interaction.dragTarget) {
            interaction.dragTarget.x = pos.x - interaction.offset.x;
            interaction.dragTarget.y = pos.y - interaction.offset.y;
        }
        
        draw();
    }

    function handleInteractionEnd(e) {
        if (!interaction.isDragging) return;
        e.preventDefault();
        
        clearTimeout(interaction.longPressTimer);
        interaction.longPressTimer = null;
        
        const pos = getMousePos(canvas, e.changedTouches ? e.changedTouches[0] : e);

        if (interaction.hasMoved) { // Drag
            const endElement = getElementAt(pos);
            if (endElement && endElement.type === 'state' && endElement.object.id !== interaction.dragTarget.id) {
                const startNode = interaction.dragTarget;
                const endNode = endElement.object;
                
                startNode.x = interaction.startPos.x - interaction.offset.x;
                startNode.y = interaction.startPos.y - interaction.offset.y;

                const label = prompt(`Transition label from ${startNode.label} to ${endNode.label}:`);
                if (label) {
                    automaton.transitions.push({ from: startNode.id, to: endNode.id, label: label });
                }
            }
        } else { // Tap/Click
            if (e.shiftKey) {
                handleLongPress();
            } else {
                if (interaction.dragTarget) {
                    interaction.dragTarget.isFinal = !interaction.dragTarget.isFinal;
                }
            }
        }
        
        interaction.isDragging = false;
        interaction.dragTarget = null;
        draw();
    }
    
    function handleLongPress() {
        if (interaction.dragTarget) {
            if (confirm(`Are you sure you want to delete the state ${interaction.dragTarget.label}?`)) {
                automaton.states = automaton.states.filter(s => s.id !== interaction.dragTarget.id);
                automaton.transitions = automaton.transitions.filter(t => t.from !== interaction.dragTarget.id && t.to !== interaction.dragTarget.id);
                if (interaction.dragTarget.isInitial && automaton.states.length > 0) {
                    automaton.states[0].isInitial = true;
                }
            }
            interaction.isDragging = false;
            interaction.dragTarget = null;
            clearTimeout(interaction.longPressTimer);
            interaction.longPressTimer = null;
            draw();
        }
    }

    canvas.addEventListener('mousedown', handleInteractionStart);
    canvas.addEventListener('mousemove', handleInteractionMove);
    canvas.addEventListener('mouseup', handleInteractionEnd);
    canvas.addEventListener('mouseout', (e) => { if (interaction.isDragging) handleInteractionEnd(e); });
    canvas.addEventListener('touchstart', handleInteractionStart, { passive: false });
    canvas.addEventListener('touchmove', handleInteractionMove, { passive: false });
    canvas.addEventListener('touchend', handleInteractionEnd);
    canvas.addEventListener('touchcancel', handleInteractionEnd);
    
    let lastTap = 0;
    canvas.addEventListener('touchend', (e) => {
        if (interaction.hasMoved) return;
        const currentTime = new Date().getTime();
        const tapLength = currentTime - lastTap;
        if (tapLength < 300 && tapLength > 0) {
            e.preventDefault();
            handleDoubleClick(e.changedTouches[0]);
        }
        lastTap = currentTime;
    });
    canvas.addEventListener('dblclick', (e) => {
        handleDoubleClick(e);
    });
    function handleDoubleClick(e) {
        if (simulation.active) return;
        const pos = getMousePos(canvas, e);
        if (getElementAt(pos)) return;
        automaton.states.push({
            id: stateCounter, label: `q${stateCounter}`, x: pos.x, y: pos.y, radius: 30,
            isInitial: automaton.states.length === 0, isFinal: false,
        });
        stateCounter++;
        draw();
    }
    
    exportPngButton.addEventListener('click', () => {
        const link = document.createElement('a');
        link.download = 'automaton.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        showToast('Image exported successfully!', 'success');
    });
    
    generateStringsButton.addEventListener('click', () => {
        if (automaton.states.length === 0) { showToast("Draw an automaton before analyzing it!", "error"); return; }
        showToast("Language analysis in progress...", "info");
        languageAnalysisOutput.classList.add('hidden');
        fetch('api/generate_strings.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(automaton) })
        .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
        .then(result => {
            languageDescription.textContent = result.description;
            generatedStringsList.innerHTML = '';
            if (result.strings.length > 0) {
                result.strings.forEach(str => {
                    const li = document.createElement('li');
                    if (str === "") { li.className = 'empty-string'; } else { li.textContent = str; }
                    generatedStringsList.appendChild(li);
                });
            } else { generatedStringsList.innerHTML = '<li>No string found (or empty language).</li>'; }
            languageAnalysisOutput.classList.remove('hidden');
        }).catch(error => { showToast(error.message, "error"); });
    });
    
    analyzeStructureButton.addEventListener('click', () => {
        if (automaton.states.length === 0) { showToast("Draw an automaton before analyzing it!", "error"); return; }
        showToast("Language analysis in progress...", "info");
        fetch('api/analyze_structure.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(automaton) })
        .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
        .then(result => {
            analysis.active = true;
            analysis.unreachable = result.unreachable;
            analysis.dead = result.dead;
            draw();
            showToast(`Analysis completed: ${result.unreachable.length} unreachable states, ${result.dead.length} dead states.`, 'success');
        }).catch(error => { showToast(error.message, "error"); });
    });

    generateCodeButton.addEventListener('click', () => {
        if (automaton.states.length === 0) { showToast("Draw or generate a DFA first!", "error"); return; }
        const language = codeLanguageSelect.value;
        showToast(`Code generation ${language.charAt(0).toUpperCase() + language.slice(1)} in progress...`, "info");
        fetch('api/generate_code.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ automaton: automaton, language: language }) })
        .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
        .then(result => {
            generatedCodeBlock.className = `language-${result.language}`;
            generatedCodeBlock.textContent = result.code;
            Prism.highlightElement(generatedCodeBlock);
            modalContainer.classList.remove('hidden');
        }).catch(error => { showToast(error.message, "error"); });
    });

    modalCloseButton.addEventListener('click', () => { modalContainer.classList.add('hidden'); });
    savedModalCloseButton.addEventListener('click', () => { savedModalContainer.classList.add('hidden'); });
    
    copyCodeButton.addEventListener('click', () => {
        navigator.clipboard.writeText(generatedCodeBlock.textContent).then(() => {
            showToast("Code copied to clipboard!", "success");
        }).catch(err => { showToast("Error while copying.", "error"); });
    });

    function populateSavedAutomataList(automataList) {
        savedAutomataList.innerHTML = '';
        if (automataList.length === 0) {
            savedAutomataList.innerHTML = '<li>No automaton saved.</li>';
            return;
        }
        automataList.forEach(item => {
            const li = document.createElement('li');
            const infoSpan = document.createElement('span');
            infoSpan.className = 'info';
            const stateCount = item.automaton.states.length;
            const transitionCount = item.automaton.transitions.length;
            infoSpan.textContent = `Key: ${item.share_key} (${stateCount} states, ${transitionCount} transitions)`;
            const dateSpan = document.createElement('span');
            dateSpan.className = 'date';
            dateSpan.textContent = new Date(item.created_at).toLocaleString('it-IT');
            li.appendChild(infoSpan); li.appendChild(dateSpan);
            li.addEventListener('click', () => {
                automaton = item.automaton;
                stateCounter = automaton.states.length > 0 ? Math.max(...automaton.states.map(s => s.id)) + 1 : 0;
                draw();
                savedModalContainer.classList.add('hidden');
                showToast(`Automaton with key ${item.share_key} loaded!`, 'success');
            });
            savedAutomataList.appendChild(li);
        });
    }

    savedElementsButton.addEventListener('click', () => {
        showToast("Loading saved items...", "info");
        fetch('api/get_saved_automata.php')
            .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
            .then(data => {
                populateSavedAutomataList(data);
                savedModalContainer.classList.remove('hidden');
            })
            .catch(error => { showToast(error.message, "error"); });
    });

    // --- 4. LOGICA DI SIMULAZIONE E ANIMAZIONE ---
    function resetSimulation() {
        simulation.active = false;
        simulation.history = [];
        simulation.currentStep = 0;
        simulation.activeStates = [];
        if (simulation.intervalId) { clearInterval(simulation.intervalId); simulation.intervalId = null; }
        simulation.isPlaying = false;
        playPauseButton.textContent = 'Play';
        simControlsContainer.classList.add('hidden');
        analysis.active = false;
        analysis.unreachable = [];
        analysis.dead = [];
        draw();
    }
    function stepForward() {
        if (simulation.currentStep >= simulation.history.length) { if (simulation.isPlaying) pauseSimulation(); return; }
        const stepData = simulation.history[simulation.currentStep];
        simulation.activeStates = stepData.activeStates;
        const stateLabels = stepData.activeStates.map(id => automaton.states.find(s => s.id === id)?.label || `?${id}`);
        simStatus.innerHTML = `<p><strong>Step ${simulation.currentStep + 1}:</strong> ${stepData.step}<br>Active states: {${stateLabels.join(', ')}}</p>`;
        simulation.currentStep++;
        draw();
    }
    function playSimulation() {
        if (simulation.isPlaying) return;
        simulation.isPlaying = true;
        playPauseButton.textContent = 'Pause';
        simulation.intervalId = setInterval(() => {
            stepForward();
            if (simulation.currentStep >= simulation.history.length) { pauseSimulation(); }
        }, 1000);
    }
    function pauseSimulation() {
        if (!simulation.isPlaying) return;
        simulation.isPlaying = false;
        playPauseButton.textContent = 'Play';
        clearInterval(simulation.intervalId);
        simulation.intervalId = null;
    }
    testButton.addEventListener('click', () => {
        resetSimulation();
        resultOutput.innerHTML = '<p>Testing...</p>';
        fetch('api/simulate.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({automaton: automaton, testString: testStringInput.value}) })
        .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
        .then(result => {
            if (result.history && result.history.length > 0) {
                simulation.active = true;
                simulation.history = result.history;
                simControlsContainer.classList.remove('hidden');
                stepForward();
            }
            resultOutput.innerHTML = `<p style="color: ${result.accepted ? 'var(--accent-success)' : 'var(--accent-danger)'}; font-weight: bold;">${result.message}</p>`;
        }).catch(error => { resultOutput.innerHTML = `<p style="color: var(--accent-danger);">${error.message}</p>`; });
    });
    playPauseButton.addEventListener('click', () => { if (simulation.isPlaying) pauseSimulation(); else playSimulation(); });
    nextStepButton.addEventListener('click', () => { pauseSimulation(); stepForward(); });
    resetSimButton.addEventListener('click', resetSimulation);
    
    // --- 5. ALTRE OPERAZIONI ---
    clearButton.addEventListener('click', resetApplicationState);
    saveButton.addEventListener('click', () => {
        if (automaton.states.length === 0) { showToast("Draw an automaton before save it!", "error"); return; }
        showToast("Saving...", "info");
        fetch('api/save.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(automaton) })
        .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
        .then(result => {
            if (result.success && result.share_key) {
                const link = `${window.location.origin}${window.location.pathname}?load=${result.share_key}`;
                shareLinkInput.value = link;
                shareLinkContainer.classList.remove('hidden');
                showToast("Successfully saved automaton!", "success");
            }
        }).catch(error => { showToast(error.message, "error"); });
    });
    convertButton.addEventListener('click', () => {
        resetSimulation(); if (automaton.states.length === 0) { showToast("Draw an NFA!", "error"); return; }
        showToast("Conversion to DFA...", "info");
        fetch('api/convert.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(automaton) })
        .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
        .then(newDfa => {
            automaton.states = newDfa.states;
            automaton.transitions = newDfa.transitions;
            stateCounter = newDfa.states.length > 0 ? Math.max(...newDfa.states.map(s => s.id)) + 1 : 0;
            draw();
            showToast("Conversion complete!", "success");
        }).catch(error => { showToast(error.message, "error"); });
    });
    minimizeButton.addEventListener('click', () => {
        resetSimulation(); if (automaton.states.length === 0) { showToast("Draw a DFA!", "error"); return; }
        showToast("Minimizing DFA...", "info");
        fetch('api/minimize.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(automaton) })
        .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
        .then(minimalDfa => {
            automaton.states = minimalDfa.states;
            automaton.transitions = minimalDfa.transitions;
            stateCounter = minimalDfa.states.length > 0 ? Math.max(...minimalDfa.states.map(s => s.id)) + 1 : 0;
            draw();
            showToast("DFA successfully minimized!", "success");
        }).catch(error => { showToast(error.message, "error"); });
    });
    regexToNfaButton.addEventListener('click', () => {
        resetSimulation(); const regexString = regexInput.value; if (!regexString) { showToast("Insert a RegEx!", "error"); return; }
        showToast("Generating NFA...", "info");
        fetch('api/regex_to_nfa.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ regex: regexString }) })
        .then(response => response.ok ? response.json() : response.json().then(err => { throw new Error(err.error) }))
        .then(newNfa => {
            automaton.states = newNfa.states;
            automaton.transitions = newNfa.transitions;
            stateCounter = newNfa.states.length > 0 ? Math.max(...newNfa.states.map(s => s.id)) + 1 : 0;
            draw();
            showToast("NFA successfully generated!", "success");
        }).catch(error => { showToast(error.message, "error"); });
    });

    // --- AVVIO APPLICAZIONE ---
    window.addEventListener('resize', resizeCanvas);
    loadAutomatonFromUrl();
    resizeCanvas();
});

// Registrazione del Service Worker per la PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const swPath = '/regex-machina/sw.js';
        navigator.serviceWorker.register(swPath)
            .then(registration => {
                console.log('Service Worker successfully registered:', registration.scope);
            })
            .catch(error => {
                console.log('Service Worker registration failed:', error);
            });
    });
}