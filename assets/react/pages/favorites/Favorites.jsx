import Header from "@components/Header";
import Footer from "@components/Footer";
import MenuAside from "@components/MenuAside";
export default function Favorites() {

    return (
        <>
            <Header />
            <main className="h-[37.49rem] md:h-[37.9rem] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                <MenuAside />
                <section className="col-span-4 w-full mx-auto px-4 overflow-auto mt-4 text-center">
                    <h1 className="text-3xl font-bold mb-4">Mes playlists préférés</h1>
                 </section>
            </main>
            <Footer />
        </>
    );
}
