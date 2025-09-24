import { Link } from 'react-router-dom';
import apiService from '@services/apiService';
export default function Home() {

    return (
        <div className="container mx-auto p-4">
            <h1 className="text-3xl font-bold mb-4">Welcome to the Home Page</h1>
            <p className="text-lg">This is the main landing page of the application.</p>
            <button className="btn" onClick={apiService.logout}>Deconnexion</button>
            <a href="/question" className="btn ml-4">Voir les questions</a>
            <a href="/playlist" className="btn ml-4">Voir la playlist</a>
            <div className="mt-4">
                <Link to="/question" className="text-blue-500 underline">Go to Questions</Link>
            </div>
            <div className="mt-2">
                <Link to="/playlist" className="text-blue-500 underline">Go to Playlist</Link>
            </div>
        </div>
    );
}