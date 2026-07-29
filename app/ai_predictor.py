import os
import pickle
import numpy as np

# Label mapping
ISSUE_MAP = {
    0: "Normal",
    1: "High Latency",
    2: "Network Congestion",
    3: "Device Offline / Unreachable",
    4: "DDoS Attack Detected",
    5: "Suspicious Data Exfiltration"
}

RECOMMENDATION_MAP = {
    0: "No action required. Network is stable.",
    1: "Check for distance to server or background downloads. Consider switching DNS or checking ISP routing.",
    2: "Traffic is exceeding capacity. Implement QoS, check for unauthorized large downloads, or upgrade bandwidth.",
    3: "Check physical connections, power supply, and router configuration. Device might be down.",
    4: "CRITICAL: Immediately isolate the target IP. Implement firewall rate-limiting and contact your ISP for DDoS mitigation.",
    5: "WARNING: High sustained outbound bandwidth with stealthy CPU usage. Isolate device and inspect for unauthorized data transfers or malware."
}

model_path = os.path.join(os.path.dirname(__file__), '..', 'ai_engine', 'model.pkl')
model = None

def load_model():
    global model
    if os.path.exists(model_path):
        with open(model_path, 'rb') as f:
            model = pickle.load(f)
    else:
        print("Warning: ML model not found. Please run ai_engine/train_model.py first.")

def predict_network_state(latency, packet_loss, bandwidth, cpu, ram):
    """
    Predicts the state of the network.
    Returns a tuple: (issue_detected: str, recommendation: str)
    """
    global model
    if model is None:
        load_model()
        
    if model is None:
        return "Unknown", "Model not trained."
        
    # In case of real timeouts where ping3 returns None
    if latency is None or latency > 5000:
        latency = 9999
        packet_loss = 100
        
    # Prepare features
    features = np.array([[latency, packet_loss, bandwidth, cpu, ram]])
    
    prediction = model.predict(features)[0]
    
    issue = ISSUE_MAP.get(prediction, "Unknown")
    rec = RECOMMENDATION_MAP.get(prediction, "Consult network administrator.")
    
    return issue, rec
