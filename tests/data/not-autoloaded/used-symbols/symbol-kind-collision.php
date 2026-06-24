<?php

class pcntl_fork {}
function testTypeHint(pcntl_fork $arg): void {}
testTypeHint(new pcntl_fork());

function testFunctionCall(): void {
    pcntl_fork();
}
