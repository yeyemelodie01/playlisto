import apiService from '../../services/apiService';
export default function Home() {

    return (
        <div className="container mx-auto p-4">
            <h1 className="text-3xl font-bold mb-4">Welcome to the Home Page</h1>
            <p className="text-lg">This is the main landing page of the application.</p>
            <button className="btn" onClick={apiService.logout}>Deconnexion</button>
        </div>
    );
}