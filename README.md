# 📤 PairDrop

## Self-Hosted File Sharing

[PairDrop](https://pairdrop.org) is a **self-contained, open-source PHP script** for sharing files. Designed to be simple to use via a web browser, PairDrop offers maximum **privacy** as a self-hosted service.

The script requires no database, complex dependencies, or elaborate configuration. A single `index.php` file handles everything, from creating *private rooms* to managing uploads and downloads.

## Why choose PairDrop?

- ✅ **Maximum Privacy**  
Host the script and files on your server. Complete control over your data and files with no third-parties.

- 🚀 **Fast and Lightweight**  
Purely simple PHP for minimal execution time and low server load. No frameworks, **no database**.
   
- 🔑 **Private Code-Protected Rooms**  
Share files in unique, private rooms. Only those with the room code can access and view the files.

- ⚫ **Modern, Mobile Friendly Design**  
Features a modern and clean design that is **mobile-friendly** featuring both Light Mode and Dark Mode themes.

## Live Demo

Until I hear from the original author, navigate to the [PairDrop.org](https://pairdrop.org) website to find the live demo links.

## Requirements

- PHP 7.4+
- Write permissions

## Quick Installation Guide

Assuming you know how to use a PHP script, installation and use is super simple.

1. Adjust settings in the `CONFIGURATION` section of `index.php`
2. Create a directory for PairDrop on your server  
<small style="color:#f00">Ensure PHP has write permissions for this directory</small>  
3. Upload the confiugred `index.php` into the PairDrop folder
4. Done!

Open your browser and navigate to the URL where you created the directory in Step 1. Enter a room code and start sharing files!

## What is this repository?

This is a fork of [PairDrop.org](https://pairdrop.org/) with some additional changes / features. The original author of PairDrop.org calls the software Open-Source in several locations, but left absolutely no contact information on the website. I welcome contact from the original author, whose name is unknown.

In the spirit of Open-Source I wanted to share my modifications, so I've done so with this "fork". I have also removed references to calling this functionality "AirDrop-like" because it's not even close and there's [AirDrop.net](https://airdrop.net) for that.