#!/usr/bin/env bash

rm -rf build/*
cd mercadopago-custom
zip -r -D ../build/mercadopago-custom.ocmod.zip *
cd ..
cd mercadopago-standard
zip -r -D ../build/mercadopago-standard.ocmod.zip *
cd ..
cd mercadopago-ticket
zip -r -D ../build/mercadopago-ticket.ocmod.zip *
cd ..
