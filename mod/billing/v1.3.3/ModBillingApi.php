<?php

require_once 'classes/Billing.php';


/**
 * Class ModBillingApi
 */
class ModBillingApi extends Billing {

    /**
     * @param string $method
     * @param string $arguments
     * @return bool
     */
    public function __call($method, $arguments) {
        return false;
    }


    /**
     * @return array
     */
    public function getWarning() {

        $module         = $this->module;
        $this->module   = 'billing';
        $balance        = $this->getBalance();
        $operations     = $this->moduleConfig->operations->toArray();
        $enough_balance = false;
        $this->module   = $module;

        if ( ! $this->moduleConfig->is_active_date_disable) {
            return '';
        }


        foreach ($operations as $name => $operation_conf) {
            if ($balance >= $operation_conf['price']) {
                $enough_balance = true;
                break;
            }
        }

        if ( ! $enough_balance) {
            $date_disable = $this->getDateDisable();

            $d1 = new DateTime($date_disable);
            $d2 = new DateTime();

            $interval = $d1->diff($d2);
            $day_diff = $interval->format('%a');

            if ($d1 < $d2) {
                $message = $day_diff == 0
                    ? "Просрочено меньше дня"
                    : "Просрочено " . Tool::declNum($day_diff, array('день', 'дня', 'дней'));

                return array(
                    'message'      => $message,
                    'expired_days' => $day_diff,
                );

            } else {
                if ($day_diff == 0) {
                    $message = "Осталось меньше дня";

                } elseif ($day_diff == 1) {
                    $message = "Остался {$day_diff} день";

                } elseif ($day_diff >= 2 && $day_diff <= 4) {
                    $message = "Осталось {$day_diff} дня";

                } elseif ($day_diff >= 5) {
                    $message = "Осталось {$day_diff} дней";

                } else {
                    $message = '';
                }


                if ($message) {
                    return array(
                        'message'   => $message,
                        'left_days' => $day_diff,
                    );
                }
            }
        }
    }
}