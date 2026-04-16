/**
 * Tipado y Utilidades para la gestión de Agenda Médica
 * Odontoking CRM - Professional Suite
 */

export interface AvailabilityBlock {
    id?: number;
    doctor_id: number;
    date: string; // YYYY-MM-DD
    start_time: string; // HH:mm:ss o HH:mm
    end_time: string; // HH:mm:ss o HH:mm
}

export interface TimeSlot {
    doctor_id: number;
    date: string;
    start: string; // HH:mm
    end: string; // HH:mm
}

export interface SlotGeneratorConfig {
    durationMinutes: number;
}

/**
 * Generador de Slots de Disponibilidad
 */
export class ScheduleHelper {
    private static readonly DEFAULT_DURATION = 30;

    /**
     * Transforma bloques continuos en slots de tiempo fijos
     */
    public static generateSlotsFromBlocks(
        blocks: AvailabilityBlock[],
        config: SlotGeneratorConfig = { durationMinutes: ScheduleHelper.DEFAULT_DURATION }
    ): TimeSlot[] {
        if (!blocks || !Array.isArray(blocks)) return [];
        if (config.durationMinutes <= 0) throw new Error("La duración del slot debe ser mayor a 0.");

        return blocks.flatMap(block => this.splitBlockIntoSlots(block, config.durationMinutes));
    }

    /**
     * Divide un bloque individual en intervalos
     */
    private static splitBlockIntoSlots(block: AvailabilityBlock, duration: number): TimeSlot[] {
        const slots: TimeSlot[] = [];
        
        try {
            let currentMinutes = this.timeToMinutes(block.start_time);
            const endMinutes = this.timeToMinutes(block.end_time);

            if (currentMinutes >= endMinutes) {
                console.warn(`Bloque inválido detectado para el doctor ${block.doctor_id} en fecha ${block.date}: El inicio es posterior al fin.`);
                return [];
            }

            while (currentMinutes + duration <= endMinutes) {
                slots.push({
                    doctor_id: block.doctor_id,
                    date: block.date,
                    start: this.minutesToTime(currentMinutes),
                    end: this.minutesToTime(currentMinutes + duration)
                });

                currentMinutes += duration;
            }
        } catch (error) {
            console.error(`Error procesando bloque: ${error.message}`);
        }

        return slots;
    }

    /**
     * Convierte HH:mm:ss a minutos totales desde el inicio del día
     */
    private static timeToMinutes(timeStr: string): number {
        const parts = timeStr.split(':').map(Number);
        if (parts.length < 2 || parts.some(isNaN)) {
            throw new Error(`Formato de hora inválido: ${timeStr}`);
        }
        const [hours, minutes] = parts;
        return hours * 60 + minutes;
    }

    /**
     * Convierte minutos totales a formato HH:mm
     */
    private static minutesToTime(totalMinutes: number): string {
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    }
}
