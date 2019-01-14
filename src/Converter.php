<?php

namespace Tenqz\LauftrainingConverter;

class Converter {

    const KMETERS = 'km';
    const METERS = 'm';
    const METERS_SECOND = 'min/km';
    const SECONDS = 's';
    const HOURS = 'h';

    protected $moduleVideos = [];

    public function setModuleVideos($moduleVideos)
    {
        $this->moduleVideos = $moduleVideos;
    }

    public function timeToFull($seconds, $speed = false, $showUnit = true, $displayFullFormat = false) : string
    {
        $result = [];

        $hour = floor($seconds / 60 / 60);
        $seconds -= $hour * 60 * 60;
        if($hour > 0 || $displayFullFormat) {
            $result[] = ($hour < 10 ? '0' : '') . $hour;
        }

        $minute = floor($seconds / 60);
        $seconds -= $minute * 60;
        $result[] = ($minute < 10 ? '0' : '') . $minute;

        $seconds = floor($seconds);
        $result[] = ($seconds < 10 ? '0' : '') . $seconds;

        $result = implode(':', $result);

        return (
            ($result === '00:00' || $result === '00:00:00') ?
                '' :
                $result . ($showUnit ?
                    ($speed ?
                        self::METERS_SECOND :
                        ($hour < 1 ?
                            ($minute > 0 ? self::METERS : self::SECONDS) :
                            self::HOURS
                        )
                    ) :
                    ''
                )
        );
    }

    public function timeToFullStats($seconds, $speed = false, $showUnit = true) :string
    {
        if ($seconds == 0) {
            return "00:00:00" . self::HOURS;
        }

        return $this->timeToFull($seconds, $speed, $showUnit);
    }

    public function fulltimeToSeconds(string $time) : int
    {
        $result = 0;

        if(preg_match('/^[0-9]{2}\:[0-9]{2}\:[0-9]{2}$/', $time)) {
            $time = explode(":", $time);
            $result = ((int)$time[0] * 3600) + ((int)$time[1] * 60) + (int)$time[2];
        }

        return $result;
    }

    public function meterToFull(int $meters, $onlykm = false) : string
    {
        if($meters > 1000 || $onlykm) {
            $result = number_format (($meters/1000), 1, ',', ' ')
                 . (!$onlykm ? self::KMETERS : '');
        } else {
            $result = $meters . self::METERS;
        }

        return $result;
    }

    public function kmToMeter($km):int
    {
        $meters = 0;

        $km = str_replace(',', '.', $km);
        if(preg_match('/^[0-9.]{1,5}$/', (float)$km)) {
            $meters = (float)$km * 1000;
        }

        return (int)$meters;
    }

    protected function cutFirstSymbol($currentEQ) : string
    {
        return substr($currentEQ, 1, strlen($currentEQ));
    }

    protected function getUnit($currentEQ) : array
    {
        $result = [
            'obj' => $currentEQ,
            'unit' => self::KMETERS
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
                'unit' => self::HOURS
            ];
        }
        if(preg_match('/^S.*?$/', $currentEQ)) {
            $currentEQ = $this->cutFirstSymbol($currentEQ);
            $result = [
                'obj' => $currentEQ,
                'unit' => self::KMETERS
            ];
        }

        return $result;
    }

    public function recalculate(string $text, $module) :string
    {
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
                                throw new \Exception('EQ is error');
                            $resultEQ = eval($currentEQ);
                            if($unit['unit'] === self::METERS_SECOND) {
                                $resultEQ = $resultEQ * 1000 / 60;
                                $unitMeter = self::METERS_SECOND;
                            }
                            if($unit['unit'] === self::KMETERS) {
                                $resultEQ = $resultEQ / 1000;
                                $unitMeter = self::KMETERS;
                            }
                            if($unit['unit'] === self::HOURS) {
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

        return $text;
    }

    public function recalculateFront($text, $module)
    {
        if(preg_match('/\[coach\]/', $text)) {
            return '';
        }
        $beforeText =  stristr($text, "in", true);
        $text = str_replace($beforeText, "<strong>".$beforeText."</strong>", $text);
        $text = str_replace('[loops]', (int)$module['loops'], $text);
        $text = str_replace('[txt]', $module['text'], $text);
        $text = str_replace('[video]', (isset($this->moduleVideos[$module['video']]) ? $this->moduleVideos[$module['video']]['name'] : $module['video']), $text);

        if(preg_match('/\[dur1V\]/', $text)) {
            $text = str_replace('[dur1V]', $this->timeToFull($module['dur_1'], true), $text);
        } else {
            $text = str_replace('[dur1]', $this->timeToFull($module['dur_1']), $text);
        }
        if(preg_match('/\[dur2V\]/', $text)) {
            $text = str_replace('[dur2V]', $this->timeToFull($module['dur_2'], true), $text);
        } else {
            $text = str_replace('[dur2]', $this->timeToFull($module['dur_2']), $text);
        }
        if(preg_match('/\[dur3V\]/', $text)) {
            $text = str_replace('[dur3V]', $this->timeToFull($module['dur_3'], true), $text);
        } else {
            $text = str_replace('[dur3]', $this->timeToFull($module['dur_3']), $text);
        }

        $text = str_replace('[dis1]', $this->meterToFull($module['dist_1']), $text);
        $text = str_replace('[dis2]', $this->meterToFull($module['dist_2']), $text);
        $text = str_replace('[dis3]', $this->meterToFull($module['dist_3']), $text);

        if(preg_match('/\[\=/', $text)) {
            preg_match_all('/\[\=(.*?)\]/', $text, $equation, PREG_SET_ORDER);
            if($equation) {
                foreach($equation as $eq) {
                    $unitMeter = '';
                    if(isset($eq[1]) && $eq[1]) {
                        $currentEQ = $eq[1];

                        $unit = $this->getUnit($currentEQ);
                        $currentEQ = $unit['obj'];

                        $currentEQ = str_replace('loops', (int)$module['loops'], $currentEQ);

                        $currentEQ = str_replace('dis1', $module['dist_1'], $currentEQ);
                        $currentEQ = str_replace('dis2', $module['dist_2'], $currentEQ);
                        $currentEQ = str_replace('dis3', $module['dist_3'], $currentEQ);
                        $currentEQ = str_replace('dur1', $module['dur_1'], $currentEQ);
                        $currentEQ = str_replace('dur2', $module['dur_2'], $currentEQ);
                        $currentEQ = str_replace('dur3', $module['dur_3'], $currentEQ);
                        $currentEQ = 'return ' . $currentEQ . ';';

                        try {
                            if(!preg_match('/^return\s[0-9()-+*\/.\s]{1,}\;$/', $currentEQ))
                                throw new \Exception('EQ is error');
                            $resultEQ = eval($currentEQ);
                            if($unit['unit'] === self::METERS_SECOND) {
                                $resultEQ = $resultEQ * 1000 / 60;
                                $unitMeter = self::METERS_SECOND;
                            }
                            if($unit['unit'] === self::KMETERS) {
                                $resultEQ = $resultEQ / 1000;
                                $unitMeter = self::KMETERS;
                            }
                            if($unit['unit'] === self::HOURS) {
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
        return $text;
    }

    public function recalculateBackDiary($text, $module)
    {

        $beforeText =  stristr($text, "in", true);
        $text = str_replace('[loops]', (int)$module['loops'], $text);
        $text = str_replace('[txt]', $module['text'], $text);
        $text = str_replace('[video]', (isset($this->moduleVideos[$module['video']]) ? $this->moduleVideos[$module['video']]['name'] : $module['video']), $text);

        if(preg_match('/\[dur1V\]/', $text)) {
            $text = str_replace('[dur1V]', $this->timeToFull($module['dur_1'], true), $text);
        } else {
            $text = str_replace('[dur1]', $this->timeToFull($module['dur_1']), $text);
        }
        if(preg_match('/\[dur2V\]/', $text)) {
            $text = str_replace('[dur2V]', $this->timeToFull($module['dur_2'], true), $text);
        } else {
            $text = str_replace('[dur2]', $this->timeToFull($module['dur_2']), $text);
        }
        if(preg_match('/\[dur3V\]/', $text)) {
            $text = str_replace('[dur3V]', $this->timeToFull($module['dur_3'], true), $text);
        } else {
            $text = str_replace('[dur3]', $this->timeToFull($module['dur_3']), $text);
        }

        $text = str_replace('[dis1]', $this->meterToFull($module['dist_1']), $text);
        $text = str_replace('[dis2]', $this->meterToFull($module['dist_2']), $text);
        $text = str_replace('[dis3]', $this->meterToFull($module['dist_3']), $text);

        if(preg_match('/\[\=/', $text)) {
            preg_match_all('/\[\=(.*?)\]/', $text, $equation, PREG_SET_ORDER);
            if($equation) {
                foreach($equation as $eq) {
                    $unitMeter = '';
                    if(isset($eq[1]) && $eq[1]) {
                        $currentEQ = $eq[1];

                        $unit = $this->getUnit($currentEQ);
                        $currentEQ = $unit['obj'];

                        $currentEQ = str_replace('loops', (int)$module['loops'], $currentEQ);

                        $currentEQ = str_replace('dis1', $module['dist_1'], $currentEQ);
                        $currentEQ = str_replace('dis2', $module['dist_2'], $currentEQ);
                        $currentEQ = str_replace('dis3', $module['dist_3'], $currentEQ);
                        $currentEQ = str_replace('dur1', $module['dur_1'], $currentEQ);
                        $currentEQ = str_replace('dur2', $module['dur_2'], $currentEQ);
                        $currentEQ = str_replace('dur3', $module['dur_3'], $currentEQ);
                        $currentEQ = 'return ' . $currentEQ . ';';

                        try {
                            if(!preg_match('/^return\s[0-9()-+*\/.\s]{1,}\;$/', $currentEQ))
                                throw new \Exception('EQ is error');
                            $resultEQ = eval($currentEQ);
                            if($unit['unit'] === self::METERS_SECOND) {
                                $resultEQ = $resultEQ * 1000 / 60;
                                $unitMeter = self::METERS_SECOND;
                            }
                            if($unit['unit'] === self::KMETERS) {
                                $resultEQ = $resultEQ / 1000;
                                $unitMeter = self::KMETERS;
                            }
                            if($unit['unit'] === self::HOURS) {
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
        return $text;
    }

    public function recalculateFrontDiary($text, $module)
    {

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
                                throw new \Exception('EQ is error');
                            $resultEQ = eval($currentEQ);
                            if($unit['unit'] === self::METERS_SECOND) {
                                $resultEQ = $resultEQ * 1000 / 60;
                                $unitMeter = self::METERS_SECOND;
                            }
                            if($unit['unit'] === self::KMETERS) {
                                $resultEQ = $resultEQ / 1000;
                                $unitMeter = self::KMETERS;
                            }
                            if($unit['unit'] === self::HOURS) {
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

        return $text;
    }


    public function returnInputsFields(string $text)
    {
        preg_match_all('/(\[[a-z]+[0-9]?+.?\])/', $text, $matches);
        return $matches[0];
    }
}
