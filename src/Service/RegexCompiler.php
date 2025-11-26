<?php
namespace App\Service;

class RegexCompiler {
    private int $stateCounter = 0;

    public function compile(string $infixRegex): array {
        $this->stateCounter = 0;
        if (empty($infixRegex)) {
            $postfix = '';
        } else {
            $postfix = $this->toPostfix($infixRegex);
        }
        return $this->buildNfaFromPostfix($postfix);
    }

    private function toPostfix(string $infix): string {
        $precedence = ['|' => 1, '.' => 2, '*' => 3]; $output = ''; $operators = [];
        $infix = preg_replace('/([a-zA-Z0-9])([a-zA-Z0-9\(])/', '$1.$2', $infix);
        $infix = preg_replace('/(\))([a-zA-Z0-9\(])/', '$1.$2', $infix);
        $infix = preg_replace('/(\*)([a-zA-Z0-9\(])/', '$1.$2', $infix);
        foreach (str_split($infix) as $token) {
            if (ctype_alnum($token)) { $output .= $token;
            } elseif ($token === '(') { array_push($operators, $token);
            } elseif ($token === ')') {
                while (!empty($operators) && end($operators) !== '(') $output .= array_pop($operators);
                if (!empty($operators) && end($operators) === '(') array_pop($operators);
                else throw new \Exception("Parentesi non corrispondenti.");
            } else {
                while (!empty($operators) && end($operators) !== '(' && ($precedence[end($operators)] ?? 0) >= ($precedence[$token] ?? 0)) {
                    $output .= array_pop($operators);
                }
                array_push($operators, $token);
            }
        }
        while (!empty($operators)) {
            $op = array_pop($operators);
            if ($op === '(') throw new \Exception("Parentesi non corrispondenti.");
            $output .= $op;
        }
        return $output;
    }

    private function buildNfaFromPostfix(string $postfix): array {
        $nfaStack = [];
        if (empty($postfix)) {
            $start = $this->createState(true, true);
            return ['states' => [$start], 'transitions' => []];
        }
        foreach (str_split($postfix) as $token) {
            if (ctype_alnum($token)) {
                $start = $this->createState(true, false); $end = $this->createState(false, true);
                $t = ['from' => $start['id'], 'to' => $end['id'], 'label' => $token];
                array_push($nfaStack, ['states' => [$start, $end], 'transitions' => [$t], 'startId' => $start['id'], 'endId' => $end['id']]);
            } elseif ($token === '.') {
                if (count($nfaStack) < 2) throw new \Exception("Errore di concatenazione.");
                $n2 = array_pop($nfaStack); $n1 = array_pop($nfaStack);
                $n1['states'][array_search($n1['endId'], array_column($n1['states'], 'id'))]['isFinal'] = false;
                $n2['states'][array_search($n2['startId'], array_column($n2['states'], 'id'))]['isInitial'] = false;
                $t = ['from' => $n1['endId'], 'to' => $n2['startId'], 'label' => 'e'];
                $new = ['states' => [...$n1['states'], ...$n2['states']], 'transitions' => [...$n1['transitions'], ...$n2['transitions'], $t], 'startId' => $n1['startId'], 'endId' => $n2['endId']];
                array_push($nfaStack, $new);
            } elseif ($token === '|') {
                if (count($nfaStack) < 2) throw new \Exception("Errore di unione.");
                $n2 = array_pop($nfaStack); $n1 = array_pop($nfaStack);
                $start = $this->createState(true, false); $end = $this->createState(false, true);
                $n1['states'][array_search($n1['startId'], array_column($n1['states'], 'id'))]['isInitial'] = false;
                $n1['states'][array_search($n1['endId'], array_column($n1['states'], 'id'))]['isFinal'] = false;
                $n2['states'][array_search($n2['startId'], array_column($n2['states'], 'id'))]['isInitial'] = false;
                $n2['states'][array_search($n2['endId'], array_column($n2['states'], 'id'))]['isFinal'] = false;
                $ts = [
                    ['from' => $start['id'], 'to' => $n1['startId'], 'label' => 'e'], ['from' => $start['id'], 'to' => $n2['startId'], 'label' => 'e'],
                    ['from' => $n1['endId'], 'to' => $end['id'], 'label' => 'e'], ['from' => $n2['endId'], 'to' => $end['id'], 'label' => 'e'],
                ];
                $new = ['states' => [$start, $end, ...$n1['states'], ...$n2['states']], 'transitions' => [...$n1['transitions'], ...$n2['transitions'], ...$ts], 'startId' => $start['id'], 'endId' => $end['id']];
                array_push($nfaStack, $new);
            } elseif ($token === '*') {
                if (count($nfaStack) < 1) throw new \Exception("Errore Kleene star.");
                $n = array_pop($nfaStack);
                $start = $this->createState(true, false); $end = $this->createState(false, true);
                $n['states'][array_search($n['startId'], array_column($n['states'], 'id'))]['isInitial'] = false;
                $n['states'][array_search($n['endId'], array_column($n['states'], 'id'))]['isFinal'] = false;
                $ts = [
                    ['from' => $start['id'], 'to' => $end['id'], 'label' => 'e'], ['from' => $start['id'], 'to' => $n['startId'], 'label' => 'e'],
                    ['from' => $n['endId'], 'to' => $end['id'], 'label' => 'e'], ['from' => $n['endId'], 'to' => $n['startId'], 'label' => 'e'],
                ];
                $new = ['states' => [$start, $end, ...$n['states']], 'transitions' => [...$n['transitions'], ...$ts], 'startId' => $start['id'], 'endId' => $end['id']];
                array_push($nfaStack, $new);
            }
        }
        if (count($nfaStack) !== 1) throw new \Exception("Espressione invalida.");
        return array_pop($nfaStack);
    }

    private function createState(bool $isInitial, bool $isFinal): array {
        $state = [
            'id' => $this->stateCounter, 'label' => 'q' . $this->stateCounter,
            'x' => 50 + ($this->stateCounter % 8) * 90, 'y' => 100 + floor($this->stateCounter / 8) * 90,
            'radius' => 30, 'isInitial' => $isInitial, 'isFinal' => $isFinal,
        ];
        $this->stateCounter++;
        return $state;
    }
}