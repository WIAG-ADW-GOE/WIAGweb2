# Jupyter Notebook Installation

This document provides a step-by-step guide to installing and setting up Jupyter Notebook on a Windows system. It includes detailed instructions for installing necessary software, including Julia and Python, configuring system environment variables, installing essential libraries for both Python and Julia, and creating a convenient shortcut for launching Jupyter Notebook.

## 1. Outline

1. [Outline](#1-outline)
2. [Credentials and Notes](#2-credentials-and-notes)
3. [Julia Installation](#3-julia-installation)
4. [Python Installation](#4-python-installation)
5. [Restart](#5-restart)
6. [Python Libraries Installation](#6-python-libraries-installation)
7. [Julia Libraries Installation](#7-julia-libraries-installation)
8. [Jupyter Notebook Shortcut Creation](#8-jupyter-notebook-shortcut-creation)

## 2. Credentials and Notes

- You need local administrator credentials to install software for all users.
- Currently, there seems to be no canonical solution for a centralized location for Julia libraries. This means that if there are multiple users on a computer, all users have to install julia and the libraries separately.

---

## 3. Julia Installation

1. Download the installer from [Julia Downloads](https://julialang.org/downloads/).  
   Example version used: [Julia 1.10.4](https://julialang-s3.julialang.org/bin/winnt/x64/1.10/julia-1.10.4-win64.exe).

2. Run the downloaded `.exe` file as Administrator (“Als Administrator ausführen”). Now you need to simply click on next at all the steps, except the first one where you need to change the location where Julia would be installed.

3. At the first installation step, change the installation directory to “C:\Program Files\JuliaXXXX” where JuliaXXXX is the name of the last folder already present with XXXX denoting the version information. In case you are using the version that is used as the example above, this installation directory would look like “C:\Program Files\Julia1.10.4”. Here XXXX is “1.10.4” which indicates the Julia version.

4. Click on the checkbox “Add Julia to PATH” and install Julia without any other changes.

5. Update the PATH environment variable:

   - Press the Windows key and search for `Systemumgebungsvariablen bearbeiten`.
   - Click “Umgebungsvariablen…”.
   - Under `Benutzervariablen für…`, double-click on the `PATH` variable.
   - Copy the Julia-related entry (it will have the text julia in it).
   - Under `Systemvariabeln`, double-click on the `PATH` variable.
   - Click `New` and paste the copied string.
   - Save changes by clicking `OK` in all windows.

---

## 4. Python Installation

1. Download the installer from [Python Downloads](https://www.python.org/downloads/).  
   Example version used: [Python 3.12.4](https://www.python.org/ftp/python/3.12.4/python-3.12.4-amd64.exe).

2. Run the `.exe` file as Administrator (“Als Administrator ausführen”). You can do this by right clicking on the `.exe` file and then clicking on the administrator option.

3. Select:

   - Admin privileges checkbox
   - Add `python.exe` to PATH checkbox

4. Choose `Custom Install`.

5. Uncheck:

   - `tcl/tk`
   - `Python test suite`

6. Continue with the installation:

   - Check `Install Python 3.12 for all users`.
   - Ensure `Precompile Standard Library` is checked.

7. Proceed through the installation.

8. Check the `Disable path length limit` option.

9. Complete the installation.

## 5. Restart

Restart your computer for the changes to take effect.

## 6. Python Libraries Installation

1. Open the Command Prompt (`Eingabeaufforderung`) as Administrator.
2. Run the following commands:

```
pip install notebook
pip install pandas asyncio aiohttp
```

## 7. Julia libraries Installation

1. Open command prompt (Eingabeaufforderung).
   a. Note: this command prompt should not be open as admin
2. Type julia and press enter. This will run the Julia language interpreter which would allow us to install libraries.
3. After julia opens up, type the “]” symbol. This will change the prompt on the left. It will look something like "... pkg>". This denotes that now julia is ready to install packages.
4. Type “add CSV DataFrame Dates” and press enter. This will start installing the libraries. Please wait until the installation takes place and then close the window.

## 8. Jupyter notebook shortcut creation

1. Open `%ALLUSERSPROFILE%\Microsoft\Windows\Start Menu\Programs` in the file explorer. Do this by copying this path above and pasting it in the File Explorer's Address bar.
2. Right click in the folder and create a new shortcut (Verknüpfung)
3. In the new window, copy paste the following in the path: “cmd /c cd C:\ && py –m jupyter notebook”
4. Rename the shortcut to “Jupyter Notebook”.

This now creates an shortcut to Jupyter Notebook in the Windows search box. Now when you type Jupyter Notebook, this shortcut would be shown which can be run to open Jupyter.
