<?php
namespace App\Service;

use Exception;
use SplQueue;

class StringGenerator {
    private array $states;
    private array $transitions;
    private array $alphabet = [];
    private int $initialStateId = -1;
    private array $finalStateIds = [];

    public function __construct(array $automatonData) {
        $this->states = $automatonData['states'] ?? [];
        $this->transitions = $automatonData['transitions'] ?? [];
        
        foreach ($this->states as $state) {
            if ($state['isInitial']) $this->initialStateId = $state['id'];
            if ($state['isFinal']) $this->finalStateIds[] = $state['id'];
        }
        foreach ($this->transitions as $t) {
            if ($t['label'] !== 'e' && !in_array($t['label'], $this->alphabet)) {
                $this->alphabet[] = $t['label'];
            }
        }

        if ($this->initialStateId === -1) {
            throw new Exception("Automaton hasn't starting state.");
        }
    }
    
    // Le funzioni helper epsilonClosure e move sono necessarie qui
    private function epsilonClosure(array $statesSet): array {
        $closure = $statesSet;
        $stack = $statesSet;
        while (!empty($stack)) {
            $stateId = array_pop($stack);
            foreach ($this->transitions as $t) {
                if ($t['from'] === $stateId && $t['label'] === 'e' && !in_array($t['to'], $closure)) {
                    $closure[] = $t['to'];
                    $stack[] = $t['to'];
                }
            }
        }
        sort($closure);
        return array_unique($closure);
    }

    private function move(array $statesSet, string $symbol): array {
        $reachable = [];
        foreach ($statesSet as $stateId) {
            foreach ($this->transitions as $t) {
                if ($t['from'] === $stateId && $t['label'] === $symbol) {
                    $reachable[] = $t['to'];
                }
            }
        }
        return array_unique($reachable);
    }
    
    public function generate(int $maxCount = 5, int $maxLength = 15): array {
        if (empty($this->finalStateIds)) {
            return []; // Nessuna stringa accettata
        }

        $foundStrings = [];
        
        $initialSet = $this->epsilonClosure([$this->initialStateId]);
        
        // Controlla se la stringa vuota (ε) è accettata
        if (!empty(array_intersect($initialSet, $this->finalStateIds))) {
            $foundStrings[""] = true;
        }

        $queue = new SplQueue();
        // La coda ora contiene: [ set di stati correnti, percorso stringa ]
        $queue->enqueue([$initialSet, '']);
        
        // Mappa per evitare di riesplorare lo stesso stato-potenza (set di stati)
        $visitedSets = [implode(',', $initialSet) => true];

        while (!$queue->isEmpty() && count($foundStrings) < $maxCount) {
            [$currentSet, $currentPath] = $queue->dequeue();

            if (strlen($currentPath) >= $maxLength) {
                continue;
            }

            // Esplora ogni possibile carattere dell'alfabeto
            foreach ($this->alphabet as $symbol) {
                $nextSetAfterMove = $this->move($currentSet, $symbol);
                if (empty($nextSetAfterMove)) {
                    continue;
                }
                
                $nextSet = $this->epsilonClosure($nextSetAfterMove);
                $nextSetKey = implode(',', $nextSet);
                
                if (!isset($visitedSets[$nextSetKey])) {
                    $newPath = $currentPath . $symbol;
                    
                    // Controlla se questo nuovo stato è uno stato di accettazione
                    if (!empty(array_intersect($nextSet, $this->finalStateIds))) {
                        $foundStrings[$newPath] = true;
                        if (count($foundStrings) >= $maxCount) break;
                    }

                    $visitedSets[$nextSetKey] = true;
                    $queue->enqueue([$nextSet, $newPath]);
                }
            }
        }
        
        // Ordina le stringhe per lunghezza
        $strings = array_keys($foundStrings);
        usort($strings, fn($a, $b) => strlen($a) <=> strlen($b));
        
        return $strings;
    }
    
    public function analyzeLanguage(): string {
        if (empty($this->finalStateIds)) return "Language is empty (no final state).";
        
        $initialSet = $this->epsilonClosure([$this->initialStateId]);
        $isEpsilonAccepted = !empty(array_intersect($initialSet, $this->finalStateIds));
        
        $description = $isEpsilonAccepted ? "Accept empty string (ε)." : "Don't accept empty string.";

        // Un'euristica leggermente migliore: controlla la presenza di cicli
        $hasCycle = false;
        foreach ($this->transitions as $t) {
            if ($t['from'] === $t['to']) {
                $hasCycle = true;
                break;
            }
        }
        // Questo non rileva tutti i cicli, ma è un inizio. Un'analisi completa richiederebbe una visita del grafo.
        if (!$hasCycle) {
            // Potremmo fare una visita per cicli qui... per ora usiamo una heuristica più semplice.
        }

        if (count($this->alphabet) > 0) { // Se l'alfabeto non è vuoto
             $description .= " Could accept infinite strings if automaton has got cycles.";
        } else if (!$isEpsilonAccepted) {
            $description = "Language is empty (empty alfabet and didn't accept ε).";
        }
       
        return $description;
    }
}