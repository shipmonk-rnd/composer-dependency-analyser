<?php

namespace PropertyHooks;

interface Author {}
interface HasAuthors
{
    public string $credits { get; }
    public Author $mainAuthor { get; set; }
}

class User {
    public string $username {
        set(string $value) {
            $this->username = strtolower($value);
        }
    }
}
