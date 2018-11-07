<?php

namespace Tenqz\LauftrainingConverter;

class Converter {
	    protected function timeToFull($seconds, $speed = false) {
        $result = '';

        $hour = floor($seconds / 60 / 60);
        $seconds -= $hour * 60 * 60;
        if($hour > 0) {
            $result = ($hour < 10 ? '0' . $hour : $hour) . ':';
        }

        $minute = floor($seconds / 60);
        $seconds -= $minute * 60;
        $result .= ($minute < 10 ? '0' . $minute : $minute) . ':';

        $seconds = floor($seconds);
        $result .= ($seconds < 10 ? '0' . $seconds : $seconds);

        return ($result === '00:00' ? '' : $result .
            ($speed ? 'min/km' : ($hour < 1 ? ($minute > 0 ? 'm' : 's') : 'h'))
        );
    }

    protected function meterToFull(int $meters) {
        if($meters > 1000) {
            $result = number_format (($meters/1000), 1, ',', ' ') . 'km';
        } else {
            $result = $meters . 'm';
        }

        return $result;
    }

    protected function cutFirstSymbol($currentEQ) {
        return substr($currentEQ, 1, strlen($currentEQ));
    }

    protected function getUnit($currentEQ) {
        $result = [
            'obj' => $currentEQ,
            'unit' => self::METERS
        ];

        if(preg_match('/^V.*?$/', $currentEQ)) {
            $currentEQ = $this->cutFirstSymbol($currentEQ);
            $result = [
                'obj' => $currentEQ,
                'unit' => self::METERS_SECOND
            ];
        }
        if(preg_match('/^T.*?$/', $currentEQ)) {
            $currentEQ = $this->cutFirstSymbol($currentEQ);
            $result = [
                'obj' => $currentEQ,
                'unit' => self::SECONDS
            ];
        }
        if(preg_match('/^S.*?$/', $currentEQ)) {
            $currentEQ = $this->cutFirstSymbol($currentEQ);
            $result = [
                'obj' => $currentEQ,
                'unit' => self::METERS
            ];
        }

        return $result;
    }

        public function recalculate($text, $module) {

        $text = str_replace('[loops]', (int)$module['log_loops'], $text);
        $text = str_replace('[txt]', $module['text'], $text);
        $text = str_replace('[video]', (isset($this->moduleVideos[$module['video']]) ? $this->moduleVideos[$module['video']]['name'] : $module['video']), $text);

        if(preg_match('/\[dur1V\]/', $text)) {
            $text = str_replace('[dur1V]', $this->timeToFull($module['log_dur_1'], true), $text);
        } else {
            $text = str_replace('[dur1]', $this->timeToFull($module['log_dur_1']), $text);
        }
        if(preg_match('/\[dur2V\]/', $text)) {
            $text = str_replace('[dur2V]', $this->timeToFull($module['log_dur_2'], true), $text);
        } else {
            $text = str_replace('[dur2]', $this->timeToFull($module['log_dur_2']), $text);
        }
        if(preg_match('/\[dur3V\]/', $text)) {
            $text = str_replace('[dur3V]', $this->timeToFull($module['log_dur_3'], true), $text);
        } else {
            $text = str_replace('[dur3]', $this->timeToFull($module['log_dur_3']), $text);
        }

        $text = str_replace('[dis1]', $this->meterToFull($module['log_dist_1']), $text);
        $text = str_replace('[dis2]', $this->meterToFull($module['log_dist_2']), $text);
        $text = str_replace('[dis3]', $this->meterToFull($module['log_dist_3']), $text);

        if(preg_match('/\[\=/', $text)) {
            preg_match_all('/\[\=(.*?)\]/', $text, $equation, PREG_SET_ORDER);
            if($equation) {
                foreach($equation as $eq) {
                    $unitMeter = '';
                    if(isset($eq[1]) && $eq[1]) {
                        $currentEQ = $eq[1];

                        $unit = $this->getUnit($currentEQ);
                        $currentEQ = $unit['obj'];

                        $currentEQ = str_replace('loops', (int)$module['log_loops'], $currentEQ);

                        $currentEQ = str_replace('dis1', $module['log_dist_1'], $currentEQ);
                        $currentEQ = str_replace('dis2', $module['log_dist_2'], $currentEQ);
                        $currentEQ = str_replace('dis3', $module['log_dist_3'], $currentEQ);
                        $currentEQ = str_replace('dur1', $module['log_dur_1'], $currentEQ);
                        $currentEQ = str_replace('dur2', $module['log_dur_2'], $currentEQ);
                        $currentEQ = str_replace('dur3', $module['log_dur_3'], $currentEQ);
                        $currentEQ = 'return ' . $currentEQ . ';';

                        try {
                            if(!preg_match('/^return\s[0-9()-+*\/.\s]{1,}\;$/', $currentEQ))
                                throw new Exception('EQ is error');
                            $resultEQ = eval($currentEQ);
                            if($unit['unit'] === self::METERS_SECOND) {
                                $resultEQ = $resultEQ * 1000 / 60;
                                $unitMeter = self::METERS_SECOND;
                            }
                            if($unit['unit'] === self::METERS) {
                                $resultEQ = $resultEQ / 1000;
                                $unitMeter = self::METERS;
                            }
                            if($unit['unit'] === self::SECONDS) {
                                $resultEQ = $this->timeToFull($resultEQ);
                            }
                            $resultEQ = number_format(ceil($resultEQ * 100) / 100, 2);
                        } catch(\Exception $e) {
                            $resultEQ = 0;
                        }

                        $text = preg_replace('/(\[=.*?\])/', $resultEQ . '&nbsp;' . $unitMeter, $text, 1);
                    }
                }
            }
        }

        $result = $text;

        return $result;
    }
}
