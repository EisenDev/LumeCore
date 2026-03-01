#!/bin/bash
sudo ss -tlnp | grep 8080 > check_port.txt
sudo cat check_port.txt
