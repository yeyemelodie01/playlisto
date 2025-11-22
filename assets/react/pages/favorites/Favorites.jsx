import Header from "@components/Header";
import Footer from "@components/Footer";
import MenuAside from "@components/MenuAside";
import apiServices from "@services/apiService";
import { useEffect, useState } from "react";
import { Link } from "react-router-dom";

export default function Favorites() {
    const [playlists, setPlaylists] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchFavorites = async () => {
            try {
                const { data } = await apiServices.get("api/favorites");
                const playlists = Array.isArray(data) ? data : data["hydra:member"] ?? [];

                setPlaylists(playlists);
            } catch (error) {
                console.error("Error fetching favorites", error);
            } finally {
                setLoading(false);
            }
        };

        fetchFavorites();
    }, []);

    const handleRemoveFavorite = async (playlistId) => {
        try {
            await apiServices.delete(`api/playlist/${playlistId}/delete-to-favorite`);

            setPlaylists((prev) => prev.filter((p) => p.id !== playlistId));
        } catch (error) {
            console.error("Error removing favorite", error);
        }
    };

    if (loading) {
        return (
            <>
                <Header />
                <main className="h-[37.49rem] md:h-[37.9rem] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                    <MenuAside />
                    <section className="col-span-4 w-full mx-auto px-4 overflow-auto mt-4 text-center">
                        <h1 className="text-3xl font-bold mb-4">Mes playlists préférés</h1>
                        <p>Chargement des favoris...</p>
                    </section>
                </main>
                <Footer />
            </>
        );
    }

    if (playlists.length === 0) {
        return (
            <>
                <Header />
                <main className="h-[37.49rem] md:h-[37.9rem] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                    <MenuAside />
                    <section className="col-span-4 w-full mx-auto px-4 overflow-auto mt-4 text-center">
                        <h1 className="text-3xl font-bold mb-4">Mes playlists préférés</h1>
                        <p>Aucune playlist en favoris.</p>
                    </section>
                </main>
                <Footer />
            </>
        );
    }
    return (
        <>
            <Header />
            <main className="h-[37.49rem] md:h-[37.9rem] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                <MenuAside />
                <section className="col-span-4 w-full mx-auto px-4 overflow-auto mt-4 text-center">
                    <h1 className="text-3xl font-bold mb-4">Mes playlists préférés</h1>
                    <ul className="flex flex-wrap gap-6 justify-center mt-6">
                        {playlists.map(playlist => (
                            <li key={playlist.id} className="w-64">
                                <div className="">
                                    <Link to={`/playlist/${playlist.id}`} state={{ isFavorite: true }} className="relative block h-32 w-full bg-base-100 rounded-lg shadow hover:shadow-md transition p-4 border border-base-300 hover:border-primary cursor-pointer">
                                        <h2 className="text-lg font-semibold mb-2">
                                            {playlist.title ?? `Playlist #${playlist.id}`}
                                        </h2>
                                        <p className="text-sm mb-1 opacity-70">
                                            {playlist.trackCount ?? 0} tracks
                                        </p>
                                        {playlist.createdAt && (
                                            <p className="text-xs text-gray-500">
                                                Created: {new Date(playlist.createdAt).toLocaleString()}
                                            </p>
                                        )}
                                        <button
                                            className="absolute top-2 right-2"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                handleRemoveFavorite(playlist.id);
                                            }} aria-label="Retirer des favoris">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor" className="size-6">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                        </button>
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            </main>
            <Footer/>
        </>
    );
}
