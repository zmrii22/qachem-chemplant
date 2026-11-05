<?php
/** QACHEM calculation service. All formulas are isolated here for QA review/testing. */
function qc_num($v): float {
    if ($v === '' || $v === null || !is_numeric($v)) { throw new InvalidArgumentException('A required numeric input is missing or invalid.'); }
    return (float)$v;
}
function qc_div(float $a, float $b): float {
    if (abs($b) < 1e-12) { throw new InvalidArgumentException('Division by zero is not allowed.'); }
    return $a / $b;
}
function qc_calculate(int $methodKey, array $i): array {
    $a=$i['A']??null; $b=$i['B']??null; $c=$i['C']??null; $d=$i['D']??null; $e=$i['E']??null; $f=$i['F']??null;
    switch ($methodKey) {
        case 1: // TSC = ((C-A)/B)*100
            $dry = qc_num($c) - qc_num($a);
            return ['intermediate'=>$dry, 'result'=>qc_div($dry, qc_num($b))*100, 'unit'=>'%'];
        case 2: // CA 1.0M HCl: V=(D-C); result V*1.6
            $v = qc_num($d) - qc_num($c);
            return ['intermediate'=>$v, 'result'=>$v*1.6, 'unit'=>'%'];
        case 3: // CA TG HF 0.1M: V=(D-C); result V*0.16
            $v = qc_num($d) - qc_num($c);
            return ['intermediate'=>$v, 'result'=>$v*0.16, 'unit'=>'%'];
        case 4: // PTC volume=(D-C)+(E-D) = E-C; result volume*1.0*5
            $v = (qc_num($d)-qc_num($c)) + (qc_num($e)-qc_num($d));
            return ['intermediate'=>$v, 'result'=>$v*5, 'unit'=>'%'];
        case 5: // Chlorine = volume*0.142
            $v = qc_num($d)-qc_num($c);
            return ['intermediate'=>$v, 'result'=>$v*0.142, 'unit'=>'%'];
        case 6: // Foaming = H2-H1 (D-C)
            return ['intermediate'=>null, 'result'=>qc_num($d)-qc_num($c), 'unit'=>'cm'];
        case 7: // Sieve residue = ((W3-W2)/W1)*100
            return ['intermediate'=>null, 'result'=>qc_div(qc_num($c)-qc_num($b), qc_num($a))*100, 'unit'=>'%'];
        case 8: // Acidity = (V3+0.56)/0.31, V3=C-B per source columns
            $v = qc_num($c)-qc_num($b);
            return ['intermediate'=>$v, 'result'=>($v+0.56)/0.31, 'unit'=>'%'];
        case 9: return ['intermediate'=>null, 'result'=>qc_num($a), 'unit'=>'CFU/mL'];
        case 10: return ['intermediate'=>null, 'result'=>qc_num($a), 'unit'=>'pH'];
        case 11: return ['intermediate'=>null, 'result'=>qc_num($a), 'unit'=>'cP'];
        case 12: // In-house sodium hypo: source says volume*25*0.0142
            $v = qc_num($d)-qc_num($c);
            return ['intermediate'=>$v, 'result'=>$v*25*0.0142, 'unit'=>'%'];
        case 13: return ['intermediate'=>null, 'result'=>qc_num($a), 'unit'=>'g/mL'];
        case 16: // Supplier sodium hypochlorite: Available Chlorine = Volume*3.72/(2*Weight)
            $v = qc_num($d)-qc_num($c);
            return ['intermediate'=>$v, 'result'=>qc_div($v*3.72, 2*qc_num($a)), 'unit'=>'%'];
        case 17: // Supplier caustic soda: NaOH = Volume*4/Weight
            $v = qc_num($d)-qc_num($c);
            return ['intermediate'=>$v, 'result'=>qc_div($v*4, qc_num($a)), 'unit'=>'%'];
        case 14: // Incoming powder moisture = ((A+B)-C)/(A+B)*100
            $den = qc_num($a)+qc_num($b);
            return ['intermediate'=>null, 'result'=>qc_div($den-qc_num($c), $den)*100, 'unit'=>'%'];
        case 15: // Dry sieve mesh particle = ((C-A)/B)*100
            return ['intermediate'=>null, 'result'=>qc_div(qc_num($c)-qc_num($a), qc_num($b))*100, 'unit'=>'%'];
        default: throw new InvalidArgumentException('Unknown calculation method.');
    }
}
function qc_average(array $values): ?float {
    $valid = array_values(array_filter($values, fn($v)=>$v!=='' && $v!==null && is_numeric($v)));
    if (!$valid) return null;
    return array_sum(array_map('floatval',$valid))/count($valid);
}
