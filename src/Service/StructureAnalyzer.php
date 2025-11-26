<?php
namespace App\Service;

use Exception;

class StructureAnalyzer {
    private array $states;
    private array $transitions;
    private int $initialStateId = -1;
    private array $finalStateIds = [];
    private array $stateIds = [];

    public function __construct(array $automatonData) {
        $this->states = $automatonData['states'] ?? [];
        $this->transitions = $automatonData['transitions'] ?? [];
        
        if (empty($this->states)) {
            throw new Exception("L'automa è vuoto.");
        }

        foreach ($this->states as $state) {
            $this->stateIds[] = $state['id'];
            if ($state['isInitial']) $this->initialStateId = $state['id'];
            if ($state['isFinal']) $this->finalStateIds[] = $state['id'];
        }

        if ($this->initialStateId === -1) {
            throw new Exception("L'automa non ha uno stato iniziale.");
        }
    }

    public function analyze(): array {
        $reachable = $this->findReachableStates();
        $unreachable = array_diff($this->stateIds, $reachable);

        $productive = $this->findProductiveStates();
        $dead = array_diff($this->stateIds, $productive);

        // Uno stato pozzo (trap state) è uno stato morto ma raggiungibile.
        $trapStates = array_intersect($dead, $reachable);

        return [
            'unreachable' => array_values($unreachable),
            'dead' => array_values($dead),
            'trap' => array_values($trapStates)
        ];
    }

    /**
     * Trova tutti gli stati raggiungibili dallo stato iniziale.
     * Usa una visita in ampiezza (BFS).
     */
    private function findReachableStates(): array {
        $reachable = [];
        $queue = new \SplQueue();
        
        $queue->enqueue($this->initialStateId);
        $reachable[] = $this->initialStateId;

        while (!$queue->isEmpty()) {
            $currentStateId = $queue->dequeue();

            foreach ($this->transitions as $t) {
                if ($t['from'] === $currentStateId) {
                    $nextStateId = $t['to'];
                    if (!in_array($nextStateId, $reachable)) {
                        $reachable[] = $nextStateId;
                        $queue->enqueue($nextStateId);
                    }
                }
            }
        }
        return $reachable;
    }

    /**
     * Trova tutti gli stati "produttivi", cioè quelli da cui è possibile
     * raggiungere uno stato finale. Usa una BFS inversa.
     */
    private function findProductiveStates(): array {
        $productive = [];
        $queue = new \SplQueue();

        // Partiamo da tutti gli stati finali
        foreach ($this->finalStateIds as $finalId) {
            if (!in_array($finalId, $productive)) {
                $productive[] = $finalId;
                $queue->enqueue($finalId);
            }
        }

        // Creiamo un grafo inverso per efficienza
        $reversedTransitions = [];
        foreach ($this->transitions as $t) {
            $reversedTransitions[$t['to']][] = $t['from'];
        }

        while (!$queue->isEmpty()) {
            $currentStateId = $queue->dequeue();
            
            if (isset($reversedTransitions[$currentStateId])) {
                foreach ($reversedTransitions[$currentStateId] as $prevStateId) {
                    if (!in_array($prevStateId, $productive)) {
                        $productive[] = $prevStateId;
                        $queue->enqueue($prevStateId);
                    }
                }
            }
        }
        return $productive;
    }
}