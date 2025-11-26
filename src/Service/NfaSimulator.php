<?php
namespace App\Service;

class NfaSimulator {
    private array $states;
    private array $transitions;

    public function __construct(array $automatonData) {
        $this->states = $automatonData['states'] ?? [];
        $this->transitions = $automatonData['transitions'] ?? [];
    }

    public function run(string $testString): array {
        if (empty($this->states)) {
            return ['accepted' => false, 'message' => 'Automaton is empty.', 'history' => []];
        }
        $initialState = null;
        foreach ($this->states as $state) if ($state['isInitial']) $initialState = $state;
        if ($initialState === null) {
            return ['accepted' => false, 'message' => 'No starting state defined.', 'history' => []];
        }

        $history = [];
        $currentStatesSet = $this->epsilonClosure([$initialState['id']]);
        $history[] = ['step' => 'Start (ε-chiusura)', 'char' => null, 'activeStates' => $currentStatesSet];

        $stringChars = str_split($testString);
        if (empty($testString)) $stringChars = [null];

        $isBlocked = false;
        foreach ($stringChars as $char) {
            if ($char === null) break;
            $moveResult = $this->move($currentStatesSet, $char);
            $history[] = ['step' => "Reading '{$char}'", 'char' => $char, 'activeStates' => $moveResult];
            $currentStatesSet = $this->epsilonClosure($moveResult);
            $history[] = ['step' => "ε-close after '{$char}'", 'char' => 'ε', 'activeStates' => $currentStatesSet];
            if (empty($currentStatesSet)) { $isBlocked = true; break; }
        }

        $finalStatesIds = array_column(array_filter($this->states, fn($s) => $s['isFinal']), 'id');
        $intersection = array_intersect($currentStatesSet, $finalStatesIds);
        $accepted = !$isBlocked && !empty($intersection);

        $message = $isBlocked ? "Not accepted string." : ($accepted ? "String accepted." : "String refused.");
        
        return ['accepted' => $accepted, 'message' => $message, 'history' => $history];
    }
    
    private function epsilonClosure(array $statesSet): array {
        $closure = $statesSet; $stack = $statesSet;
        while (!empty($stack)) {
            $stateId = array_pop($stack);
            foreach ($this->transitions as $t) {
                if ($t['from'] === $stateId && $t['label'] === 'e' && !in_array($t['to'], $closure)) {
                    $closure[] = $t['to']; $stack[] = $t['to'];
                }
            }
        }
        return array_unique($closure);
    }

    private function move(array $statesSet, string $symbol): array {
        $reachable = [];
        foreach ($statesSet as $stateId) {
            foreach ($this->transitions as $t) {
                if ($t['from'] === $stateId && $t['label'] === $symbol) $reachable[] = $t['to'];
            }
        }
        return array_unique($reachable);
    }
}