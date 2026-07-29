from app import create_app, db, bcrypt
from app.models import User

app = create_app()

with app.app_context():
    def create_user(username, email, password, role):
        user = User.query.filter_by(username=username).first()
        if not user:
            user = User.query.filter_by(email=email).first()
            
        if not user:
            hashed_password = bcrypt.generate_password_hash(password).decode('utf-8')
            user = User(username=username, email=email, password=hashed_password, role=role)
            db.session.add(user)
            print(f"Created {role} user: {username}")
        else:
            user.role = role
            if user.email != email:
                user.email = email
            print(f"Updated {username} to {role} role.")
    
    # Create the 3 RBAC roles
    create_user('Admin', 'admin@example.com', 'admin123', 'admin')
    create_user('Analyst', 'analyst@example.com', 'analyst123', 'analyst')
    create_user('Viewer', 'viewer@example.com', 'viewer123', 'viewer')
    
    db.session.commit()
    print("Database seeding complete.")
