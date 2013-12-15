<?php

if (!session::isUser()) {
    return;
}
vote::ajaxVote('down');