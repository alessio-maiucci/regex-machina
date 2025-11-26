<?php
namespace App\Service;

use Exception;
use InvalidArgumentException;

class DfaMinimizer {
    private array $states;
    private array $transitions;
    private array $alphabet = []; // Inizializzato
    private array $transitionMap = [];
    private int $initialStateId = -1;

    public function __construct(array $dfaData) {
        if (empty($dfaData['states'])) {
            // Se non ci sono stati, non c'è nulla da fare.
            $this->states = [];
            $this->transitions = [];
            return;
        }
        
        $this->states = $dfaData['states'];
        $this->transitions = $dfaData['transitions'] ?? [];
        
        $this->validateAndPrepare();
    }
    
    private function validateAndPrepare(): void {
        // Popola l'alfabeto
        foreach ($this->transitions as $t) {
            if (!in_array($t['label'], $this->alphabet)) {
                $this->alphabet[] = $t['label'];
            }
        }

        // Costruisci la mappa delle transizioni e valida il determinismo
        foreach ($this->transitions as $t) {
            if ($t['label'] === 'e') {
                throw new InvalidArgumentException('L\'automa contiene ε-transizioni e non è un DFA.');
            }
            $key = $t['from'] . '_' . $t['label'];
            if (isset($this->transitionMap[$key])) {
                $label = $t['label'];
                $fromLabel = $this->states[array_search($t['from'], array_column($this->states, 'id'))]['label'] ?? $t['from'];
                throw new InvalidArgumentException("L'automa non è deterministico (stato {$fromLabel} ha più transizioni per '{$label}').");
            }
            $this->transitionMap[$key] = $t['to'];
        }

        // Trova lo stato iniziale
        foreach ($this->states as $s) {
            if ($s['isInitial']) {
                $this->initialStateId = $s['id'];
                break;
            }
        }
        if ($this->initialStateId === -1) {
            throw new InvalidArgumentException('Nessuno stato iniziale definito.');
        }
    }

    public function minimize(): array {
        if (empty($this->states)) {
            return ['states' => [], 'transitions' => []];
        }

        // 1. Rimuovi irraggiungibili
        $reachableStatesIds = [];
        $stack = [$this->initialStateId];
        $reachableStatesIds[] = $this->initialStateId;
        while (!empty($stack)) {
            $currId = array_pop($stack);
            foreach ($this->alphabet as $symbol) {
                $key = $currId . '_' . $symbol;
                if (isset($this->transitionMap[$key])) {
                    $nextId = $this->transitionMap[$key];
                    if (!in_array($nextId, $reachableStatesIds)) {
                        $reachableStatesIds[] = $nextId;
                        $stack[] = $nextId;
                    }
                }
            }
        }
        $this->states = array_values(array_filter($this->states, fn($s) => in_array($s['id'], $reachableStatesIds)));
        if (empty($this->states)) return ['states' => [], 'transitions' => []];
        $stateIds = array_column($this->states, 'id');
        $finalStateIds = array_column(array_filter($this->states, fn($s) => $s['isFinal']), 'id');

        // 2. Table-Filling
        $distinguishable = [];
        foreach ($stateIds as $i => $p) {
            foreach (array_slice($stateIds, $i + 1) as $q) {
                $key = min($p, $q) . '-' . max($p, $q);
                if (in_array($p, $finalStateIds) !== in_array($q, $finalStateIds)) {
                    $distinguishable[$key] = true;
                }
            }
        }
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($stateIds as $i => $p) {
                foreach (array_slice($stateIds, $i + 1) as $q) {
                    $key = min($p, $q) . '-' . max($p, $q);
                    if (isset($distinguishable[$key])) continue;
                    
                    foreach ($this->alphabet as $symbol) {
                        $p_next = $this->transitionMap[$p . '_' . $symbol] ?? null;
                        $q_next = $this->transitionMap[$q . '_' . $symbol] ?? null;
                        if ($p_next !== null && $q_next !== null && $p_next !== $q_next) {
                            $next_key = min($p_next, $q_next) . '-' . max($p_next, $q_next);
                            if (isset($distinguishable[$next_key])) {
                                $distinguishable[$key] = true;
                                $changed = true;
                                break;
                            }
                        }
                    }
                }
            }
        }

        // 3. Partizionamento
        $partitions = []; $processed = [];
        foreach ($stateIds as $p) {
            if (in_array($p, $processed)) continue;
            $newPartition = [$p];
            foreach ($stateIds as $q) {
                if ($p === $q || in_array($q, $processed)) continue;
                $key = min($p, $q) . '-' . max($p, $q);
                if (!isset($distinguishable[$key])) {
                    $newPartition[] = $q; $processed[] = $q;
                }
            }
            $processed[] = $p; sort($newPartition);
            $partitions[] = $newPartition;
        }

        // 4. Costruzione DFA minimale
        $minimalDfaStates = []; $minimalDfaTransitions = [];
        $partitionMap = []; $newIdCounter = 0;
        foreach ($partitions as $partition) {
            $newStateId = $newIdCounter++;
            $minimalDfaStates[] = [
                'id' => $newStateId, 'label' => 'M' . $newStateId,
                'x' => 150 + ($newStateId % 4) * 120, 'y' => 150 + floor($newStateId / 4) * 120,
                'radius' => 30, 'isInitial' => in_array($this->initialStateId, $partition),
                'isFinal' => !empty(array_intersect($partition, $finalStateIds))
            ];
            foreach ($partition as $oldId) $partitionMap[$oldId] = $newStateId;
        }
        foreach ($partitions as $partition) {
            $newStateId = $partitionMap[$partition[0]];
            $repOldId = $partition[0];
            foreach ($this->alphabet as $symbol) {
                $key = $repOldId . '_' . $symbol;
                if (isset($this->transitionMap[$key])) {
                    $oldTargetId = $this->transitionMap[$key];
                    if (isset($partitionMap[$oldTargetId])) {
                        $newTargetId = $partitionMap[$oldTargetId];
                        $minimalDfaTransitions[] = ['from' => $newStateId, 'to' => $newTargetId, 'label' => $symbol];
                    }
                }
            }
        }
        return ['states' => $minimalDfaStates, 'transitions' => array_values(array_unique($minimalDfaTransitions, SORT_REGULAR))];
    }
}