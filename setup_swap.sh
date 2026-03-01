#!/bin/bash
sudo dd if=/dev/zero of=/swapfile2 bs=1M count=2048
sudo chmod 600 /swapfile2
sudo mkswap /swapfile2
sudo swapon /swapfile2
echo "/swapfile2 none swap sw 0 0" | sudo tee -a /etc/fstab
free -m
