import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, classification_report
import pickle
import os

# Create synthetic dataset for network conditions
def generate_synthetic_data(num_samples=1000):
    np.random.seed(42)
    
    # Features: latency (ms), packet_loss (%), bandwidth_usage (%), cpu_usage (%), ram_usage (%)
    # Classes: 
    # 0 = Normal
    # 1 = High Latency
    # 2 = Network Congestion
    # 3 = Device Offline / Unreachable
    # 4 = DDoS Attack Detected
    # 5 = Suspicious Data Exfiltration
    
    data = []
    
    for _ in range(num_samples):
        condition = np.random.choice([0, 1, 2, 3, 4, 5], p=[0.5, 0.15, 0.15, 0.1, 0.05, 0.05])
        
        if condition == 0: # Normal
            latency = np.random.uniform(5, 50)
            packet_loss = np.random.uniform(0, 1)
            bw = np.random.uniform(10, 50)
            cpu = np.random.uniform(10, 60)
            ram = np.random.uniform(20, 70)
        elif condition == 1: # High Latency
            latency = np.random.uniform(150, 500)
            packet_loss = np.random.uniform(0, 2)
            bw = np.random.uniform(20, 60)
            cpu = np.random.uniform(10, 60)
            ram = np.random.uniform(20, 70)
        elif condition == 2: # Network Congestion
            latency = np.random.uniform(80, 200)
            packet_loss = np.random.uniform(5, 20)
            bw = np.random.uniform(80, 99)
            cpu = np.random.uniform(60, 95)
            ram = np.random.uniform(60, 90)
        elif condition == 3: # Device Offline
            latency = 9999 # Timeout indicator
            packet_loss = 100
            bw = 0
            cpu = 0
            ram = 0
        elif condition == 4: # DDoS Attack
            latency = np.random.uniform(200, 900)
            packet_loss = np.random.uniform(20, 80)
            bw = np.random.uniform(95, 100)
            cpu = np.random.uniform(90, 100)
            ram = np.random.uniform(85, 100)
        else: # Suspicious Data Exfiltration (condition 5)
            latency = np.random.uniform(10, 60)
            packet_loss = np.random.uniform(0, 1)
            bw = np.random.uniform(90, 100) # High constant bandwidth
            cpu = np.random.uniform(10, 30) # Low CPU (stealthy transfer)
            ram = np.random.uniform(20, 50)
            
        data.append([latency, packet_loss, bw, cpu, ram, condition])
        
    df = pd.DataFrame(data, columns=['latency', 'packet_loss', 'bandwidth', 'cpu', 'ram', 'label'])
    return df

def train():
    print("Generating synthetic data...")
    df = generate_synthetic_data(2000)
    
    X = df.drop('label', axis=1)
    y = df['label']
    
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    
    print("Training Random Forest Classifier...")
    model = RandomForestClassifier(n_estimators=100, random_state=42)
    model.fit(X_train, y_train)
    
    y_pred = model.predict(X_test)
    acc = accuracy_score(y_test, y_pred)
    
    print(f"Model Accuracy: {acc * 100:.2f}%")
    print("Classification Report:")
    print(classification_report(y_test, y_pred))
    
    # Save the model
    model_path = os.path.join(os.path.dirname(__file__), 'model.pkl')
    with open(model_path, 'wb') as f:
        pickle.dump(model, f)
    print(f"Model saved to {model_path}")

if __name__ == '__main__':
    train()
