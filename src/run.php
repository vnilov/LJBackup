<?php
    include_once "autoload.php";
    if (Checker::check()) {
        Runner::i()->go();
    }