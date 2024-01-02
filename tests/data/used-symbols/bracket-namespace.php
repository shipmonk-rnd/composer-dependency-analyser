<?php


namespace App1 {
    new \DateTimeImmutable();
}

namespace App2 {
    use DateTime as DateTimeAlias;

    new DateTimeAlias();
}
