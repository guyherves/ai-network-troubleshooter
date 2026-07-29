# AI-Based Network Troubleshooting System

![Python](https://img.shields.io/badge/Python-3.x-blue?style=for-the-badge&logo=python&logoColor=white)
![Flask](https://img.shields.io/badge/Flask-Web%20Framework-black?style=for-the-badge&logo=flask&logoColor=white)
![Scikit-Learn](https://img.shields.io/badge/Scikit--Learn-Machine%20Learning-orange?style=for-the-badge&logo=scikit-learn&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-purple?style=for-the-badge&logo=bootstrap&logoColor=white)

> An AI-Based Network Troubleshooting System that uses Machine Learning to monitor network health in real-time. Built with Flask, it autonomously detects anomalies like DDoS attacks or congestion and provides intelligent, actionable recommendations for IT administrators.

## ✨ Features

- **Real-Time Network Monitoring**: Continuously measures latency, packet loss, bandwidth usage, and hardware metrics (CPU/RAM).
- **AI-Powered Diagnostics**: Uses a trained Random Forest model to classify the network state (e.g., Normal, High Latency, Congestion, Device Offline).
- **Actionable Recommendations**: Provides automated troubleshooting steps based on the AI's diagnosis.
- **Premium Admin Dashboard**: A clean, responsive Bootstrap 5 interface with dark/light modes and glassmorphism styling to trigger diagnostics and view results instantly.
- **Troubleshooting History**: Maintains a log of all past diagnostic runs for auditing and historical analysis.

## 🛠️ Tech Stack

- **Backend**: Python 3, Flask, SQLAlchemy
- **Machine Learning**: Scikit-Learn, Pandas, NumPy
- **Monitoring**: `ping3`, `psutil`
- **Frontend**: HTML5, CSS3, Bootstrap 5, Chart.js
- **Database**: SQLite (Configurable to MySQL)

## Installation & Setup

1. **Clone the repository** (or copy the project files to your local machine).
2. **Install Python Requirements**:
   ```bash
   pip install -r requirements.txt
   ```
3. **Train the AI Model**:
   Before running the server for the first time, you must generate the synthetic dataset and train the Machine Learning model.
   ```bash
   python ai_engine/train_model.py
   ```
   *(This will create a `model.pkl` file in the `ai_engine/` directory.)*
4. **Start the Application**:
   ```bash
   python run.py
   ```
5. **Access the Dashboard**:
   Open your web browser and navigate to `http://localhost:5000`. Register an administrator account and log in to start running network diagnostics!

## Project Structure

- `app/`: Contains the Flask application, routes, models, and monitoring logic.
- `app/templates/`: HTML files for the dashboard and authentication.
- `app/static/`: CSS and JavaScript files.
- `ai_engine/`: Scripts to generate data and train the Random Forest Classifier.
- `config.py`: Database and application configuration.
- `run.py`: Entry point to launch the Flask server.
- `Final_Project_Report.md`: Full written documentation of the project.

## License
This project was developed for educational purposes as a Final Year Project.
