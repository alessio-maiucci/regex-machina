<?php
namespace App\Service;

class DfaConverter {
    // Implementazione simile a convert.php, ma incapsulata in una classe
    private array $nfa_states;
    private array $nfa_transitions;
    private array $alphabet = [];

    public function __construct(array $nfaData) {
        $this->nfa_states = $nfaData['states'] ?? [];
        $this->nfa_transitions = $nfaData['transitions'] ?? [];
        foreach ($this->nfa_transitions as $t) {
            if ($t['label'] !== 'e' && !in_array($t['label'], $this->alphabet)) {
                $this->alphabet[] = $t['label'];
            }
        }
    }

    public function convert(): array {
        $nfa_initialState = null;
        foreach ($this->nfa_states as $s) if ($s['isInitial']) $nfa_initialState = $s;
        if ($nfa_initialState === null) throw new \Exception('NFA senza stato iniziale.');
        
        $nfa_finalStatesIds = array_column(array_filter($this->nfa_states, fn($s) => $s['isFinal']), 'id');

        $dfa_states = []; $dfa_transitions = []; $unprocessed = [];
        $dfa_state_map = []; $dfa_state_counter = 0;

        $s0_nfa_set = $this->epsilonClosure([$nfa_initialState['id']]);
        if (empty($s0_nfa_set)) return ['states' => [], 'transitions' => []];

        $s0_id_str = implode(',', $s0_nfa_set);
        $dfa_state_map[$s0_id_str] = $dfa_state_counter;
        $unprocessed[] = $s0_nfa_set;
        
        $dfa_states[] = [
            'id' => $dfa_state_counter, 'label' => 'D' . $dfa_state_counter,
            'x' => 150, 'y' => 150, 'radius' => 30, 'isInitial' => true,
            'isFinal' => !empty(array_intersect($s0_nfa_set, $nfa_finalStatesIds))
        ];
        $dfa_state_counter++;

        while (!empty($unprocessed)) {
            $current_nfa_set = array_shift($unprocessed);
            $current_dfa_state_id = $dfa_state_map[implode(',', $current_nfa_set)];

            foreach ($this->alphabet as $symbol) {
                $move_result = $this->move($current_nfa_set, $symbol);
                if (empty($move_result)) continue;
                $next_nfa_set = $this->epsilonClosure($move_result);
                $next_nfa_set_id_str = implode(',', $next_nfa_set);

                if (!isset($dfa_state_map[$next_nfa_set_id_str])) {
                    $dfa_state_map[$next_nfa_set_id_str] = $dfa_state_counter;
                    $unprocessed[] = $next_nfa_set;
                    $dfa_states[] = [
                        'id' => $dfa_state_counter, 'label' => 'D' . $dfa_state_counter,
                        'x' => 150 + ($dfa_state_counter % 4) * 120, 'y' => 150 + floor($dfa_state_counter / 4) * 120,
                        'radius' => 30, 'isInitial' => false,
                        'isFinal' => !empty(array_intersect($next_nfa_set, $nfa_finalStatesIds))
                    ];
                    $dfa_state_counter++;
                }
                $next_dfa_state_id = $dfa_state_map[$next_nfa_set_id_str];
                $dfa_transitions[] = ['from' => $current_dfa_state_id, 'to' => $next_dfa_state_id, 'label' => $symbol];
            }
        }
        return ['states' => $dfa_states, 'transitions' => $dfa_transitions];
    }
    
    // Le funzioni epsilonClosure e move sono identiche a quelle di NfaSimulator
    // In un progetto reale, si potrebbero mettere in una classe "Trait" per evitare duplicazioni.
    private function epsilonClosure(array $statesSet): array {
        $closure = $statesSet; $stack = $statesSet;
        while (!empty($stack)) {
            $stateId = array_pop($stack);
            foreach ($this->nfa_transitions as $t) {
                if ($t['from'] === $stateId && $t['label'] === 'e' && !in_array($t['to'], $closure)) {
                    $closure[] = $t['to']; $stack[] = $t['to'];
                }
            }
        }
        sort($closure);
        return array_unique($closure);
    }
    private function move(array $statesSet, string $symbol): array {
        $reachable = [];
        foreach ($statesSet as $stateId) {
            foreach ($this->nfa_transitions as $t) {
                if ($t['from'] === $stateId && $t['label'] === $symbol) $reachable[] = $t['to'];
            }
        }
        return array_unique($reachable);
    }
}