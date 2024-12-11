# Jupyter Notebook Installation

This document provides a step-by-step guide to installing and setting up Jupyter Notebook on a Windows system. It includes detailed instructions for installing necessary software, including Julia and Python, configuring system environment variables, installing essential libraries for both Python and Julia, and creating a convenient shortcut for launching Jupyter Notebook. Troubleshooting steps and links to official documentation are also provided for a seamless experience.

## 1. Outline

1. [Credentials and Notes](#2-credentials-and-notes): Provides essential information about administrator credentials required for installations and the limitations of Julia library sharing across users.
2. [Julia Installation](#3-julia-installation): Step-by-step guide to downloading, installing, and configuring Julia, including adding it to the system PATH.
3. [Python Installation](#4-python-installation): Detailed instructions for installing Python and ensuring compatibility through system configurations.
4. [Restart](#5-restart)
5. [Python Libraries Installation](#6-python-libraries-installation): Guides you through installing required Python libraries using pip.
6. [Julia Libraries Installation](#7-julia-libraries-installation): Provides commands to install necessary Julia libraries for Jupyter Notebook.
7. [Jupyter Notebook Shortcut Creation](#8-jupyter-notebook-shortcut-creation): Instructions for creating a shortcut to launch Jupyter Notebook conveniently and an advanced alternative using `jupyter-lab`.
8. [Troubleshooting](#9-troubleshooting): Covers potential issues during installation and usage with solutions.
9. [References](#10-references): Links to official documentation for Julia, Python, and Jupyter Notebook for further troubleshooting.

## 2. Credentials and Notes

- **Administrator Credentials**: You need local administrator credentials to install software for all users.
- **Julia Libraries Limitation**: Julia does not allow easy sharing of libraries across users by default, which means libraries need to be installed individually for each user.

## 3. Julia Installation

1. Download the installer from [Julia Downloads](https://julialang.org/downloads/). As the time of writing this document, I would recommend Julia 1.10.4. Please [download it here](https://julialang-s3.julialang.org/bin/winnt/x64/1.10/julia-1.10.4-win64.exe).

2. Run the `.exe` file as Administrator (“Als Administrator ausführen”).

3. Change the installation directory to:

   ```
   C:\Program Files\JuliaXXXX
   ```

   Replace `XXXX` with the version number (e.g., `1.10.4`, if used the example julia version above).

4. Check the box for **“Add Julia to PATH”** and proceed with the installation. You can then simply click on `Finish`.

5. Update the PATH environment variable:

   - Search for `Systemumgebungsvariablen bearbeiten` and open it.
   - Click **“Umgebungsvariablen…”**.
   - Under `Benutzervariablen für…`, double-click on the `PATH` variable.
   - Copy the Julia-related entry from the list of entries. This entry will have julia written in it.
   - Under `Systemvariabeln`, double-clickthe `PATH` variable.
   - Add the copied string as a new entry here.
   - Save changes by clicking **OK** in all windows.

6. **Verify Installation (Optional)**:  
   - Open Command Prompt (for eg: powershell)
   - Without changing the path, run the following command:

      ```bash
      julia --version
      ```

   If Julia is installed correctly, it will display the version number.

7. **Common Issues and Fixes**:
   - **Issue**: Julia is not recognized in the command line.  
     **Solution**: Ensure that the PATH variable was updated and saved correctly. Restart your computer if necessary.

## 4. Python Installation

1. Download the installer from [Python Downloads](https://www.python.org/downloads/).  
   Example version used: [Python 3.12.4](https://www.python.org/ftp/python/3.12.4/python-3.12.4-amd64.exe).

2. Run the `.exe` file as Administrator (“Als Administrator ausführen”).

3. During installation:

   - Check the boxes for **Admin privileges** and **Add python.exe to PATH**.
   - Choose **Custom Install** and uncheck the boxes:
     - `tcl/tk`
     - `Python test suite`
   - Check **Install Python 3.12 for all users** and **Precompile Standard Library**.

4. Select **Disable path length limit** and then complete the installation.

## 5. Restart

Restart your computer to apply all changes from the Julia and Python installations.

## 6. Python Libraries Installation

*Note: please open this document again to copy the commands easier.*

1. Open the Command Prompt as Administrator.
   - To do this, search for "Eingabeaufforderung" on the Windows search bar.
   - Right click on it. This will show you an option called “Als Administrator ausführen” which will allow you to open Command Prompt as Administrator.
2. Run the following commands to install required libraries:
   ```bash
   py -m pip install notebook pandas asyncio aiohttp tqdm
   ```
   Do this by copying this line and pasting it in the command prompt and then pressing enter. Please wait until the installation is completed.

## 7. Julia Libraries Installation

1. Open the Command Prompt but this time not as Administrator. The last prompt was opened as Administrator.
2. Run Julia by typing:
   ```bash
   julia
   ```
3. Enter Julia's package manager by typing `]`. You will see a prompt similar:
   ```
   ... pkg>
   ```
4. Install libraries by running:
   ```julia
   add CSV DataFrames Dates
   ```
5. **Common Issues and Fixes**:
   - **Issue**: Network errors during installation.  
     **Solution**: Ensure you have a stable internet connection and retry the installation.

## 8. Jupyter Notebook Shortcut Creation

1. Create a new shortcut (`Verknüpfung`). To do this, right-click on an empty space on the desktop. Then click on `Neu` and then `Verknüpfung`.
2. Use the following as the shortcut path:
   ```
   cmd /k cd C:\ && py -m jupyter notebook
   ```

   *Note: In case have problems by copy-pasting the line above. Please delete the hyphen and type it again on your keyboard. Some browsers may use a different unicode for the hypen and cause this issue.*
3. Rename the shortcut to **“Jupyter Notebook”**.
4. Right click on the created shortcut. Then click on "An start anheften". This would link the shortcut to the start menu so that you can search for it more easily.
5. You can double click on the short cut to see if it works.

You can now navigate go the jupyter files on your computer and run them.

#### Advanced Alternative (Optional):

To use Jupyter Lab (an enhanced version of Jupyter Notebook), open the command prompt and run:

```bash
jupyter lab
```

## 9. Troubleshooting

### Common Issues:

1. **Julia or Python Not Recognized**:

   - Verify that PATH variables are correctly configured.
   - Restart your computer if changes do not take effect.

2. **Library Installation Errors**:

   - Ensure a stable internet connection.
   - Use `py -m pip install --upgrade pip` if dependencies fail during Python library installation.

3. **Jupyter Notebook Does Not Launch**:

   - Verify installations by running `py -m jupyter notebook` in the command prompt.

4. **Kernel Issues in Jupyter Notebook**:
   - Run:
     ```bash
     pip install --upgrade notebook
     ```
   - Restart the notebook server.

## 10. References

- [Julia Official Documentation](https://docs.julialang.org/)
- [Python Official Documentation](https://docs.python.org/)
- [Jupyter Notebook Documentation](https://jupyter.org/documentation)
