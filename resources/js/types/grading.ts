export type GradingComponent = 'activity' | 'laboratory' | 'quiz' | 'exam' | 'project' | 'attendance' | 'recitation';
export type RoundingMode = 'nearest' | 'down' | 'up';
export type GradingFormulaSettings = {
    best_grade: number;
    worst_grade: number;
    passing_grade: number;
    failed_grade: number;
    midterm_weight: number;
    final_weight: number;
    decimal_places: number;
    period_rounding: RoundingMode;
    final_rounding: RoundingMode;
    replace_failing_grade: boolean;
    recitation_mode: 'bonus' | 'weighted';
    reporting_frequency: 'once_per_sem' | 'twice_per_sem';
    weights: Record<GradingComponent, number>;
    passing_rates: Record<'activity' | 'quiz' | 'exam' | 'project', number>;
    customized?: boolean;
};

export const gradingComponents: { key: GradingComponent; label: string }[] = [
    { key: 'activity', label: 'Activities' },
    { key: 'laboratory', label: 'Laboratory' },
    { key: 'quiz', label: 'Quizzes' },
    { key: 'exam', label: 'Major exams' },
    { key: 'project', label: 'Projects / reporting' },
    { key: 'attendance', label: 'Attendance' },
    { key: 'recitation', label: 'Oral recitation' },
];
