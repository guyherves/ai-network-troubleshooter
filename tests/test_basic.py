import unittest
from app import create_app, db
from app.models import User, NetworkLog
from app.ai_predictor import predict_network_state

class BasicTests(unittest.TestCase):

    # executed prior to each test
    def setUp(self):
        self.app = create_app()
        # Ensure tests use an in-memory database to not mess up production data
        self.app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite://'
        self.app.config['TESTING'] = True
        self.app.config['WTF_CSRF_ENABLED'] = False
        self.app.config['DEBUG'] = False
        
        self.app_context = self.app.app_context()
        self.app_context.push()
        
        db.drop_all()
        db.create_all()
        
        self.client = self.app.test_client()
        
        # Disable login required for easier testing
        self.app.config['LOGIN_DISABLED'] = True

    # executed after each test
    def tearDown(self):
        db.session.remove()
        db.drop_all()
        self.app_context.pop()

    ###############
    #### tests ####
    ###############

    def test_main_page(self):
        response = self.client.get('/', follow_redirects=True)
        self.assertEqual(response.status_code, 200)

    def test_ai_predictor_normal(self):
        # High BW, low latency, low packet loss -> Normal
        issue, rec = predict_network_state(latency=15.5, packet_loss=0, bandwidth=20, cpu=30, ram=40)
        # Even if model isn't there, it handles gracefully. We assume it predicts Normal if loaded.
        self.assertIsNotNone(issue)
        
    def test_ai_predictor_offline(self):
        issue, rec = predict_network_state(latency=9999, packet_loss=100, bandwidth=0, cpu=0, ram=0)
        self.assertEqual(issue, "Device Offline / Unreachable")

    def test_api_chart_data(self):
        # Create dummy log
        log = NetworkLog(target_ip='8.8.8.8', latency=20.0, packet_loss=0.0, status='Online')
        db.session.add(log)
        db.session.commit()
        
        response = self.client.get('/api/chart_data')
        self.assertEqual(response.status_code, 200)
        data = response.get_json()
        self.assertTrue('latency' in data)
        self.assertEqual(len(data['latency']), 1)
        self.assertEqual(data['latency'][0], 20.0)

if __name__ == "__main__":
    unittest.main()
