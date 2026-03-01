#!/bin/bash
grep -rn "AuditFailed::dispatch" /var/www/lumecore/app/ > search_results.txt
cat search_results.txt
