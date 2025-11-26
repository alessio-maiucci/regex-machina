<?php
namespace App\Service;

use Exception;
use InvalidArgumentException;

class CodeGenerator {
    private array $states;
    private array $transitions;
    private int $initialStateId = -1;
    private array $finalStateIds = [];

    public function __construct(array $dfaData) {
        $this->states = $dfaData['states'] ?? [];
        $this->transitions = $dfaData['transitions'] ?? [];
        $this->validate();
    }

    private function validate(): void {
        if (empty($this->states)) throw new InvalidArgumentException("L'automa è vuoto.");
        
        $transitionMapCheck = [];
        foreach ($this->transitions as $t) {
            if ($t['label'] === 'e') throw new InvalidArgumentException('Il generatore di codice supporta solo DFA (no ε-transizioni).');
            $key = $t['from'] . '_' . $t['label'];
            if (isset($transitionMapCheck[$key])) {
                $fromLabel = "ID " . $t['from']; // Fallback
                foreach($this->states as $s) if ($s['id'] === $t['from']) $fromLabel = $s['label'];
                throw new InvalidArgumentException("L'automa non è deterministico (stato {$fromLabel} ha più transizioni per '{$t['label']}').");
            }
            $transitionMapCheck[$key] = $t['to'];
        }

        foreach ($this->states as $s) {
            if ($s['isInitial']) $this->initialStateId = $s['id'];
            if ($s['isFinal']) $this->finalStateIds[] = $s['id'];
        }
        if ($this->initialStateId === -1) throw new InvalidArgumentException('Nessuno stato iniziale definito.');
    }

    public function generate(string $language): string {
        switch ($language) {
            case 'javascript':
                return $this->generateJavaScript();
            case 'python':
                return $this->generatePython();
            case 'java':
                return $this->generateJava();
            case 'cpp':
                return $this->generateCppOptimized();
            default:
                throw new InvalidArgumentException("Linguaggio '{$language}' non supportato.");
        }
    }

    private function generateJavaScript(): string {
        $transitionTable = [];
        foreach ($this->transitions as $t) {
            $from = 'q' . $t['from']; $to = 'q' . $t['to'];
            $transitionTable[$from][$t['label']] = $to;
        }
        $initial = 'q' . $this->initialStateId;
        $finals = array_map(fn($id) => 'q' . $id, $this->finalStateIds);
        $jsonTransitions = json_encode($transitionTable, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $jsonFinals = json_encode($finals);

        return <<<JS
/**
 * Funzione generata da RegEx-Machina per JavaScript.
 * Valida una stringa basandosi su un DFA.
 * @param {string} inputString La stringa da validare.
 * @returns {boolean} True se la stringa è accettata, altrimenti false.
 */
function validateString(inputString) {
    const transitions = {$jsonTransitions};
    const finalStates = new Set({$jsonFinals});
    
    let currentState = '{$initial}';

    for (const char of inputString) {
        if (transitions[currentState]?.[char]) {
            currentState = transitions[currentState][char];
        } else {
            return false;
        }
    }

    return finalStates.has(currentState);
}
JS;
    }
    
    private function generatePython(): string {
        $initial = 'q' . $this->initialStateId;
        
        $transitionTable = [];
        foreach ($this->transitions as $t) {
            $from = 'q' . $t['from']; $to = 'q' . $t['to'];
            $transitionTable[$from][$t['label']] = $to;
        }

        // Costruzione manuale della stringa del dizionario per robustezza
        $pyTransitions = "{\n";
        foreach ($transitionTable as $fromState => $targetMap) {
            $pyTransitions .= "        '{$fromState}': {\n";
            foreach ($targetMap as $char => $toState) {
                $escapedChar = addslashes($char);
                $pyTransitions .= "            '{$escapedChar}': '{$toState}',\n";
            }
            $pyTransitions .= "        },\n";
        }
        $pyTransitions .= "    }";

        // Costruzione manuale del set di stati finali
        if (empty($this->finalStateIds)) {
            $pyFinals = "set()";
        } else {
            $finalItems = implode(', ', array_map(fn($id) => "'q" . $id . "'", $this->finalStateIds));
            $pyFinals = "{{$finalItems}}";
        }

        return <<<PYTHON
# Funzione generata da RegEx-Machina per Python.
# Valida una stringa basandosi su un DFA.

def validate_string(input_string: str) -> bool:
    """
    Valida una stringa basandosi su un DFA.
    :param input_string: La stringa da validare.
    :return: True se la stringa è accettata, altrimenti False.
    """
    transitions = {$pyTransitions}
    final_states = {$pyFinals}
    
    current_state = "{$initial}"
    
    for char in input_string:
        current_state = transitions.get(current_state, {}).get(char)
        if current_state is None:
            return False
            
    return current_state in final_states
PYTHON;
    }

    private function generateJava(): string {
        $initial = 'q' . $this->initialStateId;
        $finalsStr = empty($this->finalStateIds) ? '' : '"' . implode('", "', array_map(fn($id) => 'q' . $id, $this->finalStateIds)) . '"';
        
        $mapInit = "";
        $transitionGroups = [];
        foreach($this->transitions as $t) $transitionGroups[$t['from']][] = $t;
        
        foreach($transitionGroups as $fromId => $ts) {
            $from = 'q' . $fromId;
            $mapInit .= "        transitions.put(\"{$from}\", Map.ofEntries(\n";
            $puts = [];
            foreach($ts as $t) {
                $to = 'q' . $t['to'];
                $char = addslashes($t['label']);
                $puts[] = "            Map.entry('{$char}', \"{$to}\")";
            }
            $mapInit .= implode(",\n", $puts);
            $mapInit .= "\n        ));\n";
        }

        return <<<JAVA
import java.util.Map;
import java.util.Set;

// Classe generata da RegEx-Machina per Java (Java 9+ per Map.ofEntries).
// Valida una stringa basandosi su un DFA.
public class DfaValidator {

    private static final Map<String, Map<Character, String>> transitions;
    private static final Set<String> finalStates = Set.of({$finalsStr});
    private static final String initialState = "{$initial}";

    static {
        transitions = new java.util.HashMap<>();
{$mapInit}
    }

    public boolean validate(String inputString) {
        String currentState = initialState;

        for (char c : inputString.toCharArray()) {
            currentState = transitions.getOrDefault(currentState, Map.of()).get(c);
            if (currentState == null) {
                return false;
            }
        }

        return finalStates.contains(currentState);
    }
}
JAVA;
    }

    private function generateCppOptimized(): string {
        $initial = 'q' . $this->initialStateId;
        
        $switchCases = "";
        $transitionGroups = [];
        foreach($this->transitions as $t) $transitionGroups[$t['from']][] = $t;
        
        foreach($transitionGroups as $fromId => $ts) {
            $from = 'q' . $fromId;
            $switchCases .= "            case State::{$from}:\n";
            $switchCases .= "                switch(c) {\n";
            foreach($ts as $t) {
                $to = 'q' . $t['to'];
                $char = addslashes($t['label']);
                if ($char === '\\') $char = '\\\\';
                $switchCases .= "                    case '{$char}': currentState = State::{$to}; break;\n";
            }
            $switchCases .= "                    default: return false;\n";
            $switchCases .= "                }\n";
            $switchCases .= "                break;\n";
        }
        
        $stateEnums = implode(",\n    ", array_map(fn($s) => 'q' . $s['id'], $this->states));

        $finalChecks = "";
        foreach ($this->finalStateIds as $id) {
            $finalChecks .= "        if (s == State::q{$id}) return true;\n";
        }
        if (empty($finalChecks)) $finalChecks = "        // Nessuno stato finale.\n        return false;";

        return <<<CPP
#include <string>
#include <vector>

// Classe generata da RegEx-Machina per C++ (ottimizzata).
// Valida una stringa basandosi su un DFA usando enum e switch-case per performance.
class DfaValidator {
private:
    enum class State {
        {$stateEnums}
    };

public:
    DfaValidator() {}

    bool validate(const std::string& inputString) {
        State currentState = State::{$initial};

        for (char c : inputString) {
            switch(currentState) {
{$switchCases}
                default:
                    // Questo caso non dovrebbe essere raggiunto in un DFA completo
                    return false;
            }
        }
        
        return isFinal(currentState);
    }

private:
    bool isFinal(State s) const {
{$finalChecks}
    }
};
CPP;
    }
}