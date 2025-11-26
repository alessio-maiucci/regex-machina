<?php
namespace App\Service;

use Exception;

class NfaSimplifier {
    private array $states;
    private array $transitions;
    private array $alphabet = [];
    private ?array $initialState = null;
    private array $originalFinalStateIds = [];

    public function __construct(array $nfaData) {
        $this->states = $nfaData['states'] ?? [];
        $this->transitions = $nfaData['transitions'] ?? [];

        foreach ($this->states as $state) {
            if ($state['isInitial']) $this->initialState = $state;
            if ($state['isFinal']) $this->originalFinalStateIds[] = $state['id'];
        }
        foreach ($this->transitions as $t) {
            if ($t['label'] !== 'e' && !in_array($t['label'], $this->alphabet)) {
                $this->alphabet[] = $t['label'];
            }
        }
    }

    public function simplify(): array {
        if ($this->initialState === null) {
            return ['states' => [], 'transitions' => []];
        }

        $newStateCounter = 0;
        $newStates = [];
        $newTransitions = [];
        
        $unprocessedSets = new \SplQueue();
        $setMap = []; // Mappa da "stringa di ID" a "nuovo ID stato"

        // 1. Crea il primo stato del nuovo automa
        $initialSet = $this->calculateEpsilonClosure([$this->initialState['id']]);
        $initialSetKey = implode(',', $initialSet);

        $setMap[$initialSetKey] = $newStateCounter;
        $newStates[] = $this->createStateObject($newStateCounter++, true, $initialSet);
        $unprocessedSets->enqueue($initialSet);

        // 2. Processa gli insiemi di stati finché la coda non è vuota
        while (!$unprocessedSets->isEmpty()) {
            $currentSet = $unprocessedSets->dequeue();
            $currentSetKey = implode(',', $currentSet);
            $currentNewStateId = $setMap[$currentSetKey];

            foreach ($this->alphabet as $symbol) {
                // Calcola lo stato di destinazione (move + epsilonClosure)
                $moveResult = $this->move($currentSet, $symbol);
                if (empty($moveResult)) {
                    continue; // Nessuna transizione per questo simbolo
                }
                $nextSet = $this->calculateEpsilonClosure($moveResult);
                $nextSetKey = implode(',', $nextSet);
                
                // Se questo insieme non è mai stato visto, crea un nuovo stato
                if (!isset($setMap[$nextSetKey])) {
                    $setMap[$nextSetKey] = $newStateCounter;
                    $newStates[] = $this->createStateObject($newStateCounter++, false, $nextSet);
                    $unprocessedSets->enqueue($nextSet);
                }

                // Aggiungi la transizione
                $nextNewStateId = $setMap[$nextSetKey];
                $newTransitions[] = [
                    'from' => $currentNewStateId,
                    'to' => $nextNewStateId,
                    'label' => $symbol
                ];
            }
        }
        
        // Assegna posizioni casuali per la visualizzazione
        foreach ($newStates as &$state) {
            $state['x'] = rand(50, 750);
            $state['y'] = rand(50, 550);
        }
        unset($state);

        return [
            'states' => $newStates,
            'transitions' => $newTransitions
        ];
    }
    
    private function createStateObject(int $id, bool $isInitial, array $originalIds): array {
        $isFinal = !empty(array_intersect($originalIds, $this->originalFinalStateIds));
        return [
            'id' => $id,
            'label' => 's' . $id,
            'x' => 0, 'y' => 0, // Posizioni verranno assegnate dopo
            'radius' => 30,
            'isInitial' => $isInitial,
            'isFinal' => $isFinal
        ];
    }
    
    private function calculateEpsilonClosure(array $statesSet): array {
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
}