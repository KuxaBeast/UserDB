<?php

namespace App\Presenters;

use Nette;
use Tracy\Debugger;

/**
 * Error presenter.
 */
class ErrorPresenter extends BasePresenter
{
    /**
     * @param  \Exception
     * @return void
     */
    public function renderDefault($exception)
    {
        $this->template->message = '';
        if ($exception instanceof Nette\Application\BadRequestException) {
            $code = $exception->getCode();
            // load template 403.latte or 404.latte or ... 4xx.latte
            $this->setView(in_array($code, array(403, 404, 405, 410, 500)) ? $code : '4xx');
            // log to access.log
            Debugger::log("HTTP code $code: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}", 'access');
        } else {
            if ($exception instanceof Nette\Mail\SmtpException) {
                $this->template->message = $exception->getMessage();
            }
            $this->setView('500'); // load template 500.latte
            Debugger::log($exception, Debugger::EXCEPTION); // and log exception
        }

        if ($this->isAjax()) { // AJAX request? Note this error in payload.
            $this->payload->error = true;
            $this->terminate();
        }
    }
}
