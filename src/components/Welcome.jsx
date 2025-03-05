import { motion } from 'framer-motion';
import { ArrowDownIcon, UserGroupIcon, CurrencyDollarIcon, UsersIcon } from '@heroicons/react/24/outline';

function Welcome() {
    const fadeIn = {
        initial: { opacity: 0, y: 20 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: 0.6 }
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-800">
            {/* Hero Section */}
            <div className="relative h-screen flex items-center justify-center overflow-hidden">
                {/* Background Animation */}
                <motion.div
                    className="absolute inset-0 z-0"
                    animate={{
                        scale: [1, 1.1, 1],
                        rotate: [0, 5, 0],
                    }}
                    transition={{
                        duration: 20,
                        repeat: Infinity,
                        repeatType: "reverse"
                    }}
                >
                    <div className="absolute inset-0 bg-gradient-to-br from-black to-purple-900 opacity-50" />
                </motion.div>

                {/* Content */}
                <div className="relative z-10 text-center px-4">
                    <motion.img
                        src="/logo.png"
                        alt="Logo"
                        className="w-32 h-32 mx-auto mb-8"
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        transition={{ type: "spring", stiffness: 260, damping: 20 }}
                    />
                    <motion.h1
                        className="text-5xl md:text-7xl font-bold text-white mb-6"
                        {...fadeIn}
                    >
                        Bienvenue sur Solifin
                    </motion.h1>
                    <motion.p
                        className="text-xl md:text-2xl text-gray-300 max-w-2xl mx-auto mb-12"
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.3, duration: 0.6 }}
                    >
                        Découvrez une nouvelle façon de gérer vos finances avec intelligence et simplicité
                    </motion.p>

                    {/* Stats Section */}
                    <motion.div
                        className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto mb-12"
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.4, duration: 0.6 }}
                    >
                        <div className="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                            <UserGroupIcon className="w-8 h-8 text-purple-400 mx-auto mb-2" />
                            <div className="text-2xl font-bold text-white">1,234</div>
                            <div className="text-gray-300">Membres Totaux</div>
                        </div>
                        <div className="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                            <CurrencyDollarIcon className="w-8 h-8 text-green-400 mx-auto mb-2" />
                            <div className="text-2xl font-bold text-white">€50M+</div>
                            <div className="text-gray-300">Commissions Versées</div>
                        </div>
                        <div className="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6">
                            <UsersIcon className="w-8 h-8 text-blue-400 mx-auto mb-2" />
                            <div className="text-2xl font-bold text-white">890</div>
                            <div className="text-gray-300">Membres Actifs</div>
                        </div>
                    </motion.div>

                    {/* CTA Buttons */}
                    <motion.div
                        className="flex flex-col sm:flex-row gap-4 justify-center"
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.6, duration: 0.6 }}
                    >
                        <motion.button
                            whileHover={{ scale: 1.05 }}
                            whileTap={{ scale: 0.95 }}
                            className="px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-full font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-300"
                        >
                            Commencer
                        </motion.button>
                        <motion.button
                            whileHover={{ scale: 1.05 }}
                            whileTap={{ scale: 0.95 }}
                            className="px-8 py-3 bg-white bg-opacity-20 text-white rounded-full font-semibold text-lg backdrop-blur-sm hover:bg-opacity-30 transition-all duration-300"
                        >
                            En savoir plus
                        </motion.button>
                    </motion.div>
                </div>

                {/* Scroll Indicator */}
                <motion.div
                    className="absolute bottom-8 left-1/2 transform -translate-x-1/2"
                    animate={{
                        y: [0, 10, 0],
                    }}
                    transition={{
                        duration: 1.5,
                        repeat: Infinity,
                    }}
                >
                    <ArrowDownIcon className="w-8 h-8 text-white opacity-70" />
                </motion.div>
            </div>
        </div>
    );
}

export default Welcome; 