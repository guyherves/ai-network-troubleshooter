import os
import zipfile

def zipdir(path, ziph):
    for root, dirs, files in os.walk(path):
        if '__pycache__' in root or '.git' in root:
            continue
        for file in files:
            if file.endswith('.db') or file == 'Final_Project_Submission.zip' or file == 'zip_project.py':
                continue
            file_path = os.path.join(root, file)
            ziph.write(file_path, os.path.relpath(file_path, path))

if __name__ == '__main__':
    zipf = zipfile.ZipFile('Final_Project_Submission.zip', 'w', zipfile.ZIP_DEFLATED)
    zipdir('.', zipf)
    zipf.close()
    print("Project zipped successfully!")
