<?php

function legal_privacy(): void
{
    render('legal/privacy', ['pageTitle' => 'Privacy policy']);
}

function legal_terms(): void
{
    render('legal/terms', ['pageTitle' => 'Terms & upload rules']);
}
